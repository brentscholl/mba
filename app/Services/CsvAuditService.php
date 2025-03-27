<?php

namespace App\Services;

use App\Models\File;
use App\Models\AuditReportItem;
use Illuminate\Support\Facades\DB;

class CsvAuditService
{
    public function handle(File $file): void
    {
        $report = [
            'code_description_mismatches' => $this->checkDescriptionVariants($file),
            'price_inconsistencies' => $this->checkPriceConsistency($file),
            'line_total_mismatches' => $this->checkLineItemTotals($file),
            'duplicate_charges' => $this->checkDuplicateCharges($file),
            'high_unit_prices' => $this->checkHighUnitPrices($file),
            'suspiciously_high_quantities' => $this->checkExcessiveQuantities($file),
            'multiple_same_day_charges' => $this->checkSameDayDuplicates($file),
            'same_code_multiple_patients' => $this->checkCodeSpammingAcrossPatients($file),
            'missing_payment_info' => $this->checkMissingPayments($file),
            'code_usage_frequency' => $this->checkCodeOveruse($file),
        ];

        foreach ($report as $key => $data) {
            AuditReportItem::updateOrCreate(
                ['file_id' => $file->id, 'key' => $key],
                [
                    'title' => $data['title'],
                    'count' => count($data['items']),
                    'items' => $data['items'],
                ]
            );
        }
    }

    protected function checkDescriptionVariants(File $file): array
    {
        return [
            'title' => 'Code Description Mismatches',
            'items' => DB::table('invoices')
                ->select('HCPCsCode', DB::raw('COUNT(DISTINCT Description) as description_count'))
                ->where('file_id', $file->id)
                ->groupBy('HCPCsCode')
                ->having('description_count', '>', 1)
                ->get()
                ->toArray(),
        ];
    }

    protected function checkPriceConsistency(File $file): array
    {
        return [
            'title' => 'Price Inconsistencies',
            'items' => DB::table('invoices')
                ->select('HCPCsCode', DB::raw('COUNT(DISTINCT ProviderAmountEach) as price_count'))
                ->where('file_id', $file->id)
                ->groupBy('HCPCsCode')
                ->having('price_count', '>', 1)
                ->get()
                ->toArray(),
        ];
    }

    protected function checkLineItemTotals(File $file): array
    {
        return [
            'title' => 'Line Item Total Mismatches',
            'items' => DB::table('invoices')
                ->select('id', 'OrderNumber', 'HCPCsCode', 'BilledQuantity', 'ProviderAmountEach', 'ProviderAmountTotal')
                ->where('file_id', $file->id)
                ->whereRaw('ROUND(ProviderAmountEach * BilledQuantity, 2) != ROUND(ProviderAmountTotal, 2)')
                ->get()
                ->toArray(),
        ];
    }

    protected function checkDuplicateCharges(File $file): array
    {
        $items = DB::table('invoices')
            ->select(
                'PatientID',
                'InsuredInsId',
                'HCPCsCode',
                'BilledQuantity',
                'PaymentDate',
                DB::raw('GROUP_CONCAT(DISTINCT Description) as descriptions'),
                DB::raw('COUNT(*) as duplicate_count')
            )
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'InsuredInsId', 'HCPCsCode', 'BilledQuantity', 'PaymentDate')
            ->having('duplicate_count', '>', 1)
            ->get()
            ->map(function ($row) {
                $row->descriptions = explode(',', $row->descriptions);
                return (array) $row;
            })
            ->toArray();

        return [
            'title' => 'Duplicate Charges',
            'items' => $items,
        ];
    }

    protected function checkHighUnitPrices(File $file, float $threshold = 500.00): array
    {
        return [
            'title' => 'High Unit Prices',
            'items' => DB::table('invoices')
                ->select('HCPCsCode', 'Description', 'ProviderAmountEach')
                ->where('file_id', $file->id)
                ->where('ProviderAmountEach', '>', $threshold)
                ->get()
                ->toArray(),
        ];
    }

    protected function checkExcessiveQuantities(File $file, int $threshold = 100): array
    {
        return [
            'title' => 'Suspiciously High Quantities',
            'items' => DB::table('invoices')
                ->select('HCPCsCode', 'Description', 'BilledQuantity', 'PatientID')
                ->where('file_id', $file->id)
                ->where('BilledQuantity', '>', $threshold)
                ->get()
                ->toArray(),
        ];
    }

    protected function checkSameDayDuplicates(File $file): array
    {
        return [
            'title' => 'Multiple Same-Day Charges',
            'items' => DB::table('invoices')
                ->select('PatientID', 'PaymentDate', 'HCPCsCode', DB::raw('COUNT(*) as count'))
                ->where('file_id', $file->id)
                ->groupBy('PatientID', 'PaymentDate', 'HCPCsCode')
                ->having('count', '>', 1)
                ->get()
                ->toArray(),
        ];
    }

    protected function checkCodeSpammingAcrossPatients(File $file, int $threshold = 30): array
    {
        return [
            'title' => 'Code Used Across Many Patients',
            'items' => DB::table('invoices')
                ->select('HCPCsCode', DB::raw('COUNT(DISTINCT PatientID) as patient_count'))
                ->where('file_id', $file->id)
                ->groupBy('HCPCsCode')
                ->having('patient_count', '>', $threshold)
                ->get()
                ->toArray(),
        ];
    }

    protected function checkMissingPayments(File $file): array
    {
        return [
            'title' => 'Missing Payment Information',
            'items' => DB::table('invoices')
                ->select('id', 'OrderNumber', 'PaymentNumber', 'PaymentDate', 'PaymentType')
                ->where('file_id', $file->id)
                ->where(function ($query) {
                    $query->whereNull('PaymentDate')
                        ->orWhereNull('PaymentNumber')
                        ->orWhereNull('PaymentType');
                })
                ->get()
                ->toArray(),
        ];
    }

    protected function checkCodeOveruse(File $file): array
    {
        return [
            'title' => 'Most Frequently Billed Codes',
            'items' => DB::table('invoices')
                ->select('HCPCsCode', 'Description', DB::raw('COUNT(*) as usage_count'))
                ->where('file_id', $file->id)
                ->groupBy('HCPCsCode', 'Description')
                ->orderByDesc('usage_count')
                ->limit(10)
                ->get()
                ->toArray(),
        ];
    }
}
