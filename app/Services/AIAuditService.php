<?php

namespace App\Services;

use App\Models\File;
use App\Jobs\RunAIAuditChunk;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AIAuditService
{
    public function handle(File $file): void
    {
        Log::info('AIAuditService: Starting AI audits for file ID: '.$file->id);

        $jobs = collect()
            ->merge($this->checkUnrelatedProcedureCodes($file))
            ->merge($this->checkUpcoding($file))
            ->merge($this->checkModifierMisuse($file))
            ->merge($this->checkUnrealisticFrequencies($file))
            ->merge($this->checkTemplateBilling($file))
            ->merge($this->checkExcessiveDMECharges($file))
            ->merge($this->checkSuspiciousLanguage($file));

        Bus::batch($jobs->all())
            ->then(function () use ($file) {
                $file->update(['status' => 'done']);
                Log::info("✅ All AI audit jobs complete. File status updated to 'done' (File ID: {$file->id})");
            })
            ->name("AI Audit for File #{$file->id}")
            ->dispatch();
    }

    public function handleSingleAudit(File $file, string $auditKey): void
    {
        Log::info("AIAuditService: Running single audit [{$auditKey}] for file ID: {$file->id}");

        $map = [
            'unrelated_procedure_codes' => 'checkUnrelatedProcedureCodes',
            'upcoding'                  => 'checkUpcoding',
            'modifier_misuse'           => 'checkModifierMisuse',
            'unrealistic_frequencies'   => 'checkUnrealisticFrequencies',
            'template_billing'          => 'checkTemplateBilling',
            'dme_check'                 => 'checkExcessiveDMECharges',
            'suspicious_language'       => 'checkSuspiciousLanguage',
        ];

        if (! array_key_exists($auditKey, $map)) {
            Log::error("AIAuditService: Unknown audit key [{$auditKey}]");
            throw new \InvalidArgumentException("Unknown audit type: {$auditKey}");
        }

        $method = $map[$auditKey];
        $jobs = $this->{$method}($file);

        Bus::batch($jobs)
            ->then(function () use ($file, $auditKey) {
                // Only update to done if all audits are done
                if ($file->auditReports()->where('type', 'ai')->count() > 0) {
                    $file->update(['status' => 'done']);
                }

                Log::info("✅ AI audit '{$auditKey}' complete for file ID {$file->id}");
            })
            ->name("AI Audit ({$auditKey}) for File #{$file->id}")
            ->dispatch();
    }

    protected function checkUnrelatedProcedureCodes(File $file): array
    {
        $rows = DB::table('invoices')
            ->select('id', 'PatientID', 'Description')
            ->where('file_id', $file->id)
            ->get()
            ->groupBy('PatientID')
            ->map(fn($group) => [
                'PatientID'    => $group->first()->PatientID,
                'descriptions' => $group->pluck('Description')->unique()->values()->all(),
                'invoice_ids'  => $group->pluck('id')->unique()->values()->all(),
            ])
            ->values()
            ->toArray();

        return collect($rows)
            ->chunk(10)
            ->map(fn($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'unrelated_procedure_codes',
                auditTitle: 'Unrelated Procedure Codes',
                auditType: 'unrelated_procedure_codes',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkUpcoding(File $file): array
    {
        $rows = DB::table('invoices')
            ->select('id', 'PatientID', 'HCPCsCode', 'Description', 'ProviderAmountEach')
            ->where('file_id', $file->id)
            ->get()
            ->groupBy('PatientID')
            ->map(function ($group) {
                return [
                    'PatientID'   => $group->first()->PatientID,
                    'codes'       => $group->map(fn($r) => [
                        'code'        => $r->HCPCsCode,
                        'description' => $r->Description,
                        'price'       => $r->ProviderAmountEach,
                    ])->toArray(),
                    'invoice_ids' => $group->pluck('id')->unique()->values()->all(),
                ];
            })
            ->values()
            ->toArray();

        return collect($rows)
            ->chunk(10)
            ->map(fn($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'upcoding_detection',
                auditTitle: 'Potential Upcoding',
                auditType: 'upcoding',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkModifierMisuse(File $file): array
    {
        $rows = DB::table('invoices')
            ->select('id', 'PatientID', 'HCPCsCode', 'Modifier', 'Description')
            ->where('file_id', $file->id)
            ->whereNotNull('Modifier')
            ->where('Modifier', '!=', '')
            ->get()
            ->groupBy('PatientID')
            ->map(function ($group) {
                return [
                    'PatientID'   => $group->first()->PatientID,
                    'modifiers'   => $group->map(fn($r) => [
                        'code'        => $r->HCPCsCode,
                        'modifier'    => $r->Modifier,
                        'description' => $r->Description,
                    ])->toArray(),
                    'invoice_ids' => $group->pluck('id')->unique()->values()->all(),
                ];
            })
            ->values()
            ->toArray();

        return collect($rows)
            ->chunk(10)
            ->map(fn($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'modifier_misuse',
                auditTitle: 'Suspicious Modifier Usage',
                auditType: 'modifier_misuse',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkUnrealisticFrequencies(File $file): array
    {
        // Get frequencies per patient and code
        $frequencyData = DB::table('invoices')
            ->select(
                'PatientID',
                'HCPCsCode',
                DB::raw('COUNT(*) as frequency'),
                DB::raw('GROUP_CONCAT(DISTINCT Description SEPARATOR "; ") as description')
            )
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'HCPCsCode')
            ->get()
            ->groupBy('PatientID');

        // Get invoice IDs per patient
        $invoiceIdsPerPatient = DB::table('invoices')
            ->select('PatientID', 'id')
            ->where('file_id', $file->id)
            ->get()
            ->groupBy('PatientID')
            ->map(fn($rows) => $rows->pluck('id')->unique()->values()->all());

        // Assemble final patient list
        $patients = $frequencyData->map(function ($rows, $patientId) use ($invoiceIdsPerPatient) {
            return [
                'PatientID'   => $patientId,
                'frequencies' => $rows->map(fn ($r) => [
                    'code'        => $r->HCPCsCode,
                    'description' => $r->description,
                    'frequency'   => $r->frequency,
                ])->toArray(),
                'invoice_ids' => $invoiceIdsPerPatient[$patientId] ?? [],
            ];
        })->values();

        // Chunk and dispatch jobs
        return $patients
            ->chunk(10)
            ->map(fn($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'unrealistic_frequencies',
                auditTitle: 'Unrealistic Billing Frequencies',
                auditType: 'unrealistic_frequencies',
                chunk: $chunk->values()->all(),
            ))->all();
    }


    protected function checkTemplateBilling(File $file): array
    {
        // Step 1: Get all invoices for the file
        $invoices = DB::table('invoices')
            ->select('id', 'PatientID', 'HCPCsCode')
            ->where('file_id', $file->id)
            ->get()
            ->groupBy('PatientID');

        // Step 2: Build patient-wise billing patterns
        $data = $invoices->map(function ($rows, $patientId) {
            return [
                'PatientID'   => $patientId,
                'codes'       => $rows->pluck('HCPCsCode')->unique()->sort()->values()->all(),
                'invoice_ids' => $rows->pluck('id')->unique()->values()->all(),
            ];
        })->values()->toArray();

        // Step 3: Chunk and dispatch
        return collect($data)
            ->chunk(10)
            ->map(fn($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'template_billing',
                auditTitle: 'Template Billing Pattern',
                auditType: 'template_billing',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkExcessiveDMECharges(File $file): array
    {
        $invoices = DB::table('invoices')
            ->select('id', 'PatientID', 'HCPCsCode', 'Description', 'ProviderAmountTotal')
            ->where('file_id', $file->id)
            ->where(function ($q) {
                $q->where('Description', 'like', '%wheelchair%')
                    ->orWhere('Description', 'like', '%walker%')
                    ->orWhere('Description', 'like', '%brace%');
            })
            ->get()
            ->groupBy('PatientID');

        $data = $invoices->map(function ($rows, $patientId) {
            return [
                'PatientID'   => $patientId,
                'items'       => $rows->map(fn($r) => [
                    'code'  => $r->HCPCsCode,
                    'desc'  => $r->Description,
                    'total' => $r->ProviderAmountTotal,
                ])->toArray(),
                'invoice_ids' => $rows->pluck('id')->unique()->values()->all(),
            ];
        })->values()->toArray();

        return collect($data)
            ->chunk(10)
            ->map(fn($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'excessive_dme_charges',
                auditTitle: 'Excessive DME Billing',
                auditType: 'dme_check',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkSuspiciousLanguage(File $file): array
    {
        $invoices = DB::table('invoices')
            ->select('PatientID', DB::raw('GROUP_CONCAT(DISTINCT Description SEPARATOR "; ") as descriptions'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID')
            ->get();

        $data = $invoices->map(function ($row) use ($file) {
            $invoiceIds = DB::table('invoices')
                ->where('file_id', $file->id)
                ->where('PatientID', $row->PatientID)
                ->pluck('id')
                ->unique()
                ->values()
                ->all();

            return [
                'PatientID'    => $row->PatientID,
                'descriptions' => array_filter(explode('; ', $row->descriptions)),
                'invoice_ids'  => $invoiceIds,
            ];
        })->values()->toArray();

        return collect($data)
            ->chunk(10)
            ->map(fn($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'suspicious_language',
                auditTitle: 'Suspicious Language in Descriptions',
                auditType: 'suspicious_language',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    public function buildPrompt(string $auditType, array $chunk): string
    {
        $json = json_encode($chunk, JSON_PRETTY_PRINT);

        $instructions = [
            'unrelated_procedure_codes' => [
                'title' => 'Unrelated Procedure Codes',
                'body'  => <<<TXT
For each patient:
- Infer the likely procedure or treatment based on the HCPCs codes and descriptions.
- Identify any items that appear unrelated to that procedure or treatment.
- Exclude recovery or follow-up items from being flagged as unrelated.
- Ignore generic entries like "Sales Tax", "Shipping", etc.

Return an array of JSON objects with: PatientID, procedure, unrelated_items (array), reasoning.
TXT,
            ],

            'upcoding' => [
                'title' => 'Potential Upcoding',
                'body'  => <<<TXT
For each patient:
- Flag HCPCs codes where the unit cost is significantly (80%+) higher than the typical market cost for that code.
- Provide a reasoning string including the actual cost, expected median cost, and percentage difference.

Return an array of JSON objects: PatientID, suspicious_codes (array), reasoning (string).
TXT,
            ],

            'modifier_misuse' => [
                'title' => 'Suspicious Modifier Usage',
                'body'  => <<<TXT
For each patient:
- Analyze the use of HCPCs modifiers.
- Flag any usage that appears invalid or potentially abusive.

Return an array of JSON objects: PatientID, suspicious_modifiers (array), reasoning.
TXT,
            ],

            'unrealistic_frequencies' => [
                'title' => 'Unrealistic Frequencies',
                'body'  => <<<TXT
For each patient:
- Identify HCPCs codes used with unusually high frequency compared to standard expectations.
- Explain why the frequency is unrealistic and what the expected norm is.

Return an array of JSON objects: PatientID, suspicious_code, frequency, expected_frequency, reasoning.
TXT,
            ],

            'template_billing' => [
                'title' => 'Template Billing',
                'body'  => <<<TXT
For each patient:
- Identify if the billing pattern appears to be copied from a template across patients.

Return an array of JSON objects: PatientID, suspicious (string: "True" or null), reasoning.
TXT,
            ],

            'dme_check' => [
                'title' => 'Excessive DME Charges',
                'body'  => <<<TXT
For each patient:
- Review Durable Medical Equipment (DME) charges.
- Flag excessive or unnecessary DME items.

Return an array of JSON: PatientID, excessive_items (array), reasoning.
TXT,
            ],

            'suspicious_language' => [
                'title' => 'Suspicious Language in Descriptions',
                'body'  => <<<TXT
For each patient:
- Detect vague or suspicious language in HCPCs code descriptions.

Return an array of JSON: PatientID, suspicious_phrases (array), reasoning.
TXT,
            ],
        ];

        throw_if(! isset($instructions[$auditType]), new \InvalidArgumentException("Unknown audit type: $auditType"));

        $systemPrompt = "You are a helpful AI that returns only JSON.";
        $userPrompt = $instructions[$auditType]['body']."\n\n{$json}";

        return <<<PROMPT
{$userPrompt}
PROMPT;
    }

    public function transformResult(string $auditType, array $p, mixed $parsed, string $raw): ?array
    {
        $base = [
            'data'        => [],
            'invoice_ids' => [],
        ];

        return match ($auditType) {
            'unrelated_procedure_codes' => empty($p['unrelated_items']) ? null : [
                ...$base,
                'data' => [
                    'PatientID'       => $p['PatientID'],
                    'procedure'       => $p['procedure'] ?? 'Unknown',
                    'unrelated_items' => $p['unrelated_items'],
                    'reasoning'       => $p['reasoning'] ?? $raw,
                ],
            ],

            'upcoding' => empty($p['suspicious_codes']) ? null : [
                ...$base,
                'data' => [
                    'PatientID' => $p['PatientID'],
                    'suspicious_codes' => $p['suspicious_codes'],
                    'reasoning' => $p['reasoning'] ?? $raw,
                ],
            ],

            'modifier_misuse' => empty($p['suspicious_modifiers']) ? null : [
                ...$base,
                'data' => [
                    'PatientID'            => $p['PatientID'],
                    'suspicious_modifiers' => $p['suspicious_modifiers'],
                    'reasoning'            => $p['reasoning'] ?? $raw,
                ],
            ],

            'unrealistic_frequencies' => empty($p['suspicious_code']) ? null : [
                ...$base,
                'data' => [
                    'PatientID'          => $p['PatientID'],
                    'suspicious_code'    => $p['suspicious_code'],
                    'frequency'          => $p['frequency'],
                    'expected_frequency' => $p['expected_frequency'],
                    'reasoning'          => $p['reasoning'] ?? $raw,
                ],
            ],

            'template_billing' => empty($p['suspicious']) ? null : [
                ...$base,
                'data' => [
                    'PatientID'  => $p['PatientID'],
                    'suspicious' => $p['suspicious'],
                    'reasoning'  => $p['reasoning'] ?? $raw,
                ],
            ],

            'dme_check' => empty($p['excessive_items']) ? null : [
                ...$base,
                'data' => [
                    'PatientID'       => $p['PatientID'],
                    'excessive_items' => $p['excessive_items'],
                    'reasoning'       => $p['reasoning'] ?? $raw,
                ],
            ],

            'suspicious_language' => empty($p['suspicious_phrases']) ? null : [
                ...$base,
                'data' => [
                    'PatientID'          => $p['PatientID'],
                    'suspicious_phrases' => $p['suspicious_phrases'],
                    'reasoning'          => $p['reasoning'] ?? $raw,
                ],
            ],

            default => null,
        };
    }
}
