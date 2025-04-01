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
        ini_set('memory_limit', '-1');
        Log::info('AIAuditService: Starting AI audits for file ID: '.$file->id);

        $this->dispatchAuditJobs($file, 'checkUnrelatedProcedureCodes', 'unrelated_procedure_codes', 'Unrelated Procedure Codes');
        $this->dispatchAuditJobs($file, 'checkUpcoding', 'upcoding_detection', 'Potential Upcoding');
        $this->dispatchAuditJobs($file, 'checkModifierMisuse', 'modifier_misuse', 'Suspicious Modifier Usage');
        $this->dispatchAuditJobs($file, 'checkUnrealisticFrequencies', 'unrealistic_frequencies', 'Unrealistic Billing Frequencies');
        $this->dispatchAuditJobs($file, 'checkTemplateBilling', 'template_billing', 'Template Billing Pattern');
        $this->dispatchAuditJobs($file, 'checkExcessiveDMECharges', 'dme_check', 'Excessive DME Charges');
        $this->dispatchAuditJobs($file, 'checkSuspiciousLanguage', 'suspicious_language', 'Suspicious Language');
    }

    public function handleSingleAudit(File $file, string $auditKey): void
    {
        ini_set('memory_limit', '-1');

        $map = [
            'unrelated_procedure_codes' => ['checkUnrelatedProcedureCodes', 'Unrelated Procedure Codes'],
            'upcoding' => ['checkUpcoding', 'Potential Upcoding'],
            'modifier_misuse' => ['checkModifierMisuse', 'Suspicious Modifier Usage'],
            'unrealistic_frequencies' => ['checkUnrealisticFrequencies', 'Unrealistic Billing Frequencies'],
            'template_billing' => ['checkTemplateBilling', 'Template Billing Pattern'],
            'dme_check' => ['checkExcessiveDMECharges', 'Excessive DME Charges'],
            'suspicious_language' => ['checkSuspiciousLanguage', 'Suspicious Language'],
        ];

        if (! isset($map[$auditKey])) {
            throw new \InvalidArgumentException("Unknown audit type: $auditKey");
        }

        [$method, $title] = $map[$auditKey];

        $this->dispatchAuditJobs($file, $method, $auditKey, $title, single: true);
    }

    protected function dispatchAuditJobs(File $file, string $method, string $auditKey, string $title, bool $single = false): void
    {
        $chunks = $this->{$method}($file);

        $batch = Bus::batch($chunks)
            ->then(function () use ($file, $auditKey, $single) {
                if ($single || $this->allAIAuditsComplete($file)) {
                    $file->update(['status' => 'done']);
                    Log::info("AIAuditService: AI audit(s) complete for file ID {$file->id}");
                }
            })
            ->name("AI Audit ({$auditKey}) for File #{$file->id}")
            ->dispatch();
    }

    protected function allAIAuditsComplete(File $file): bool
    {
        // Optional: check if all AI audit reports exist (e.g., 7 types)
        return true;
    }

    protected function checkUnrelatedProcedureCodes(File $file): array
    {
        $rows = DB::table('invoices')
            ->select('id', 'PatientID', 'Description')
            ->where('file_id', $file->id)
            ->get()
            ->groupBy('PatientID')
            ->map(fn ($group) => [
                'PatientID'    => $group->first()->PatientID,
                'descriptions' => $group->pluck('Description')->unique()->values()->all(),
                'invoice_ids'  => $group->pluck('id')->unique()->values()->all(),
            ])
            ->values()
            ->toArray();

        return collect($rows)
            ->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'unrelated_procedure_codes',
                auditTitle: 'Unrelated Procedure Codes',
                auditType: 'unrelated_procedure_codes',
                chunk: $chunk->values()->all()
            ))->all();
    }

    protected function checkUpcoding(File $file): array
    {
        $rows = DB::table('invoices')
            ->select('id', 'PatientID', 'HCPCsCode', 'Description', 'ProviderAmountEach')
            ->where('file_id', $file->id)
            ->get()
            ->groupBy('PatientID')
            ->map(fn ($group) => [
                'PatientID'   => $group->first()->PatientID,
                'codes'       => $group->map(fn ($r) => [
                    'code'        => $r->HCPCsCode,
                    'description' => $r->Description,
                    'price'       => $r->ProviderAmountEach,
                ]),
                'invoice_ids' => $group->pluck('id')->unique()->values()->all(),
            ])
            ->values();

        return $rows->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'upcoding_detection',
                auditTitle: 'Potential Upcoding',
                auditType: 'upcoding',
                chunk: $chunk->values()->all()
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
            ->map(fn ($group) => [
                'PatientID'   => $group->first()->PatientID,
                'modifiers'   => $group->map(fn ($r) => [
                    'code'        => $r->HCPCsCode,
                    'modifier'    => $r->Modifier,
                    'description' => $r->Description,
                ]),
                'invoice_ids' => $group->pluck('id')->unique()->values()->all(),
            ])
            ->values();

        return $rows->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'modifier_misuse',
                auditTitle: 'Suspicious Modifier Usage',
                auditType: 'modifier_misuse',
                chunk: $chunk->values()->all()
            ))->all();
    }

    protected function checkUnrealisticFrequencies(File $file): array
    {
        $frequencyData = DB::table('invoices')
            ->select('PatientID', 'HCPCsCode', DB::raw('COUNT(*) as frequency'), DB::raw('GROUP_CONCAT(DISTINCT Description SEPARATOR "; ") as description'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'HCPCsCode')
            ->get()
            ->groupBy('PatientID');

        $invoiceIds = DB::table('invoices')
            ->select('PatientID', 'id')
            ->where('file_id', $file->id)
            ->get()
            ->groupBy('PatientID')
            ->map(fn ($rows) => $rows->pluck('id')->unique()->values()->all());

        $rows = $frequencyData->map(function ($group, $patientId) use ($invoiceIds) {
            return [
                'PatientID'   => $patientId,
                'frequencies' => $group->map(fn ($r) => [
                    'code'        => $r->HCPCsCode,
                    'description' => $r->description,
                    'frequency'   => $r->frequency,
                ]),
                'invoice_ids' => $invoiceIds[$patientId] ?? [],
            ];
        })->values();

        return $rows->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'unrealistic_frequencies',
                auditTitle: 'Unrealistic Billing Frequencies',
                auditType: 'unrealistic_frequencies',
                chunk: $chunk->values()->all()
            ))->all();
    }

    protected function checkTemplateBilling(File $file): array
    {
        $data = DB::table('invoices')
            ->select('id', 'PatientID', 'HCPCsCode')
            ->where('file_id', $file->id)
            ->get()
            ->groupBy('PatientID')
            ->map(fn ($group, $patientId) => [
                'PatientID'   => $patientId,
                'codes'       => $group->pluck('HCPCsCode')->unique()->sort()->values()->all(),
                'invoice_ids' => $group->pluck('id')->unique()->values()->all(),
            ])
            ->values();

        return $data->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'template_billing',
                auditTitle: 'Template Billing Pattern',
                auditType: 'template_billing',
                chunk: $chunk->values()->all()
            ))->all();
    }

    protected function checkExcessiveDMECharges(File $file): array
    {
        $grouped = DB::table('invoices')
            ->select('id', 'PatientID', 'HCPCsCode', 'Description', 'ProviderAmountTotal')
            ->where('file_id', $file->id)
            ->where(function ($q) {
                $q->where('Description', 'like', '%wheelchair%')
                    ->orWhere('Description', 'like', '%walker%')
                    ->orWhere('Description', 'like', '%brace%');
            })
            ->get()
            ->groupBy('PatientID');

        $data = $grouped->map(fn ($rows, $patientId) => [
            'PatientID'   => $patientId,
            'items'       => $rows->map(fn ($r) => [
                'code'  => $r->HCPCsCode,
                'desc'  => $r->Description,
                'total' => $r->ProviderAmountTotal,
            ]),
            'invoice_ids' => $rows->pluck('id')->unique()->values()->all(),
        ])->values();

        return $data->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'excessive_dme_charges',
                auditTitle: 'Excessive DME Charges',
                auditType: 'dme_check',
                chunk: $chunk->values()->all()
            ))->all();
    }

    protected function checkSuspiciousLanguage(File $file): array
    {
        $descriptions = DB::table('invoices')
            ->select('PatientID', DB::raw('GROUP_CONCAT(DISTINCT Description SEPARATOR "; ") as descriptions'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID')
            ->get();

        $rows = $descriptions->map(function ($row) use ($file) {
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
        });

        return $rows->chunk(10)
            ->map(fn ($chunk) => new RunAIAuditChunk(
                file: $file,
                auditKey: 'suspicious_language',
                auditTitle: 'Suspicious Language in Descriptions',
                auditType: 'suspicious_language',
                chunk: $chunk->values()->all()
            ))->all();
    }
}
