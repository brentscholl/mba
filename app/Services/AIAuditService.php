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
        Log::info('AIAuditService: Starting AI audits for file ID: ' . $file->id);

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

    protected function checkUnrelatedProcedureCodes(File $file): array
    {
        $patients = DB::table('invoices')
            ->select('PatientID', DB::raw('GROUP_CONCAT(DISTINCT Description SEPARATOR "; ") as descriptions'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID')
            ->get()
            ->map(fn ($row) => [
                'PatientID' => $row->PatientID,
                'descriptions' => array_filter(explode('; ', $row->descriptions)),
            ])
            ->toArray();

        return collect($patients)
            ->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'unrelated_procedure_codes',
                auditTitle: 'Unrelated Procedure Codes',
                auditType: 'unrelated_procedure_codes',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkUpcoding(File $file): array
    {
        $data = DB::table('invoices')
            ->select('PatientID', 'HCPCsCode', 'Description', 'ProviderAmountEach')
            ->where('file_id', $file->id)
            ->get()
            ->groupBy('PatientID')
            ->map(fn ($rows) => [
                'PatientID' => $rows->first()->PatientID,
                'codes' => $rows->map(fn ($r) => [
                    'code' => $r->HCPCsCode,
                    'description' => $r->Description,
                    'price' => $r->ProviderAmountEach,
                ])->toArray()
            ])
            ->values()
            ->toArray();

        return collect($data)
            ->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'upcoding_detection',
                auditTitle: 'Potential Upcoding',
                auditType: 'upcoding',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkModifierMisuse(File $file): array
    {
        $data = DB::table('invoices')
            ->select('PatientID', 'HCPCsCode', 'Modifier', 'Description')
            ->where('file_id', $file->id)
            ->whereNotNull('Modifier')
            ->where('Modifier', '!=', '')
            ->get()
            ->groupBy('PatientID')
            ->map(fn ($rows) => [
                'PatientID' => $rows->first()->PatientID,
                'modifiers' => $rows->map(fn ($r) => [
                    'code' => $r->HCPCsCode,
                    'modifier' => $r->Modifier,
                    'description' => $r->Description,
                ])->toArray()
            ])
            ->values()
            ->toArray();

        return collect($data)
            ->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'modifier_misuse',
                auditTitle: 'Suspicious Modifier Usage',
                auditType: 'modifier_misuse',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkUnrealisticFrequencies(File $file): array
    {
        $data = DB::table('invoices')
            ->select('PatientID', 'HCPCsCode', DB::raw('COUNT(*) as frequency'), DB::raw('GROUP_CONCAT(DISTINCT Description SEPARATOR "; ") as description'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'HCPCsCode')
            ->get()
            ->groupBy('PatientID')
            ->map(fn ($rows) => [
                'PatientID' => $rows->first()->PatientID,
                'frequencies' => $rows->map(fn ($r) => [
                    'code' => $r->HCPCsCode,
                    'description' => $r->description,
                    'frequency' => $r->frequency,
                ])->toArray()
            ])
            ->values()
            ->toArray();

        return collect($data)
            ->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'unrealistic_frequencies',
                auditTitle: 'Unrealistic Billing Frequencies',
                auditType: 'unrealistic_frequencies',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkTemplateBilling(File $file): array
    {
        $data = DB::table('invoices')
            ->select('PatientID', DB::raw('GROUP_CONCAT(DISTINCT HCPCsCode ORDER BY HCPCsCode SEPARATOR ";") as codes'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID')
            ->get()
            ->map(fn ($r) => [
                'PatientID' => $r->PatientID,
                'codes' => explode(';', $r->codes),
            ])
            ->toArray();

        return collect($data)
            ->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'template_billing',
                auditTitle: 'Template Billing Pattern',
                auditType: 'template_billing',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkExcessiveDMECharges(File $file): array
    {
        $data = DB::table('invoices')
            ->select('PatientID', 'HCPCsCode', 'Description', 'ProviderAmountTotal')
            ->where('file_id', $file->id)
            ->where(function ($q) {
                $q->where('Description', 'like', '%wheelchair%')
                    ->orWhere('Description', 'like', '%walker%')
                    ->orWhere('Description', 'like', '%brace%');
            })
            ->get()
            ->groupBy('PatientID')
            ->map(fn ($rows) => [
                'PatientID' => $rows->first()->PatientID,
                'items' => $rows->map(fn ($r) => [
                    'code' => $r->HCPCsCode,
                    'desc' => $r->Description,
                    'total' => $r->ProviderAmountTotal,
                ])->toArray()
            ])
            ->values()
            ->toArray();

        return collect($data)
            ->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'excessive_dme_charges',
                auditTitle: 'Excessive DME Billing',
                auditType: 'dme_check',
                chunk: $chunk->values()->all(),
            ))->all();
    }

    protected function checkSuspiciousLanguage(File $file): array
    {
        $data = DB::table('invoices')
            ->select('PatientID', DB::raw('GROUP_CONCAT(DISTINCT Description SEPARATOR "; ") as descriptions'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID')
            ->get()
            ->map(fn ($r) => [
                'PatientID' => $r->PatientID,
                'descriptions' => array_filter(explode('; ', $r->descriptions)),
            ])
            ->toArray();

        return collect($data)
            ->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
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

        return match ($auditType) {
            'unrelated_procedure_codes' => <<<EOT
The following are billing descriptions for multiple patients. For each patient:
- Infer the likely procedure or treatment based off the HCPCs codes and descriptions
- Detect if any items are unrelated to the rest of the codes.
- Identify unrelated items that are not within the scope of the procedure or treatment, this does not include items needed for recovery or follow-up care.
- Ignore items such as Sales Tax, Shipping, etc. those are not considered unrelated.
Return an array of JSON objects do not make any statements in your response, explicitly return the JSON: PatientID, procedure, unrelated_items (array), reasoning.

$json
EOT,

            'upcoding' => <<<EOT
The following is patient invoice data that contain billing HCPCs codes, description, and amount paid. For each patient:
 - Flag any that are overpriced based on common pricing for items with same code. Only flag the patent's billing HCPCs code if the item cost is 80% higher than the median range cost for that item.
- Reasoning for flagging should include the item description, the median range cost for that item, what the patient was charged, and the percentage difference.
- The format for your reasoning string should be: "Code: {code}, Description: {description}, Charged: {provider_amount_each}, Median Cost: {median_cost}, Difference: {difference}%"
- You're response should not contain a summary or any other text, only the JSON response.
Return an array of JSON objects: PatientID, suspicious_codes (array), reasoning (string).

$json
EOT,

            'modifier_misuse' => <<<EOT
The following is patient invoice data that contain billing PatientID, HCPCs codes, description, Modifier, and Description. For each patient:
- Check if any modifiers are suspicious or incorrect.
- You're response should not contain a summary or any other text, only the JSON response.
Return an array of JSON objects: PatientID, suspicious_modifiers (array), reasoning.

$json
EOT,

            'unrealistic_frequencies' => <<<EOT
The following is patient invoice data that contain billing PatientID, and HCPCs codes. For each patient:
- Flag any codes used too frequently for each patient.
- the frequency should be an unrealistic amount commonly expected for the code.
- Your reasoning should include the code, description, the frequency, and the common expected frequency.
Return an array of JSON objects: PatientID, suspicious_code, frequency, expected_frequency, reasoning.

$json
EOT,

            'template_billing' => <<<EOT
The following is patient invoice data that contain billing PatientID, and HCPCs codes. For each patient:
- Detect if patients were billed with a template pattern.
Return an array of JSON objects: PatientID, suspicious (string: "True" or null), reasoning.

$json
EOT,

            'dme_check' => <<<EOT
The following is patient invoice data that contain billing PatientID, HCPCs codes, description, and Provider amount total. For each patient:
- Identify expensive or unnecessary DME charges.
- Identify how many times the item was billed.
- Identify the excessive items billed. If there are any excessive items, return the item as an array in the json object.
Return an array of JSON: PatientID, excessive_items (array), reasoning.

$json
EOT,

            'suspicious_language' => <<<EOT
The following is patient invoice data that contain billing PatientID, and the HCPCs code's description. For each patient:
- Flag vague or suspicious wording in descriptions.
Return an array of JSON objects: PatientID, suspicious_phrases (array), reasoning.

$json
EOT,

            default => throw new \InvalidArgumentException("Unknown audit type: $auditType"),
        };
    }

    public function transformResult(string $auditType, array $p, mixed $parsed, string $raw): ?array
    {
        $base = [
            'data' => [],
            'invoice_ids' => [],
        ];

        return match ($auditType) {
            'unrelated_procedure_codes' => empty($p['unrelated_items']) ? null : [
                ...$base,
                'data' => [
                    'PatientID' => $p['PatientID'],
                    'procedure' => $p['procedure'] ?? 'Unknown',
                    'unrelated_items' => $p['unrelated_items'],
                    'reasoning' => $p['reasoning'] ?? $raw,
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
                    'PatientID' => $p['PatientID'],
                    'suspicious_modifiers' => $p['suspicious_modifiers'],
                    'reasoning' => $p['reasoning'] ?? $raw,
                ],
            ],

            'unrealistic_frequencies' => empty($p['suspicious_code']) ? null : [
                ...$base,
                'data' => [
                    'PatientID' => $p['PatientID'],
                    'suspicious_code' => $p['suspicious_code'],
                    'frequency' => $p['frequency'],
                    'expected_frequency' => $p['expected_frequency'],
                    'reasoning' => $p['reasoning'] ?? $raw,
                ],
            ],

            'template_billing' => empty($p['suspicious']) ? null : [
                ...$base,
                'data' => [
                    'PatientID' => $p['PatientID'],
                    'suspicious' => $p['suspicious'],
                    'reasoning' => $p['reasoning'] ?? $raw,
                ],
            ],

            'dme_check' => empty($p['excessive_items']) ? null : [
                ...$base,
                'data' => [
                    'PatientID' => $p['PatientID'],
                    'excessive_items' => $p['excessive_items'],
                    'reasoning' => $p['reasoning'] ?? $raw,
                ],
            ],

            'suspicious_language' => empty($p['suspicious_phrases']) ? null : [
                ...$base,
                'data' => [
                    'PatientID' => $p['PatientID'],
                    'suspicious_phrases' => $p['suspicious_phrases'],
                    'reasoning' => $p['reasoning'] ?? $raw,
                ],
            ],

            default => null,
        };
    }


}
