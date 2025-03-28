<?php

namespace App\Services;

use App\Models\File;
use App\Models\AuditReportItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditService
{
    public function handle(File $file): void
    {
        Log::info('CsvAuditService: Starting audit for file ID: ' . $file->id);

        $this->checkDescriptionVariants($file);
        $this->checkPriceConsistency($file);
        $this->checkLineItemTotals($file);
        $this->checkDuplicateCharges($file);
        $this->checkHighUnitPrices($file);
        $this->checkExcessiveQuantities($file);
        $this->checkSameDayDuplicates($file);
        $this->checkCodeSpammingAcrossPatients($file);
        $this->checkMissingPayments($file);
        $this->checkCodeOveruse($file);
    }

    private function storeReportItem(File $file, string $key, string $title, array $items): void
    {
        AuditReportItem::updateOrCreate(
            ['file_id' => $file->id, 'key' => $key],
            [
                'title' => $title,
                'count' => count($items),
                'items' => $items,
            ]
        );
    }

    /** Detects HCPCS codes billed with multiple unique descriptions. */
    protected function checkDescriptionVariants(File $file): void
    {
        $title = 'Code Description Mismatches';

        $codes = DB::table('invoices')
            ->select('HCPCsCode')
            ->where('file_id', $file->id)
            ->groupBy('HCPCsCode')
            ->havingRaw('COUNT(DISTINCT Description) > 1')
            ->pluck('HCPCsCode');

        $items = $codes->map(function ($code) use ($file) {
            $descriptions = DB::table('invoices')
                ->where('file_id', $file->id)
                ->where('HCPCsCode', $code)
                ->distinct()
                ->pluck('Description')
                ->toArray();

            return [
                'HCPCsCode' => $code,
                'description_count' => number_format(count($descriptions)),
                //'descriptions' => $descriptions,
            ];
        })->sortByDesc('description_count')->values()->toArray();

        $this->storeReportItem($file, 'code_description_mismatches', $title, $items);
    }

    /** Finds HCPCS codes billed at multiple different unit prices. */
    protected function checkPriceConsistency(File $file): void
    {
        $title = 'Price Inconsistencies';

        $codes = DB::table('invoices')
            ->select('HCPCsCode')
            ->where('file_id', $file->id)
            ->groupBy('HCPCsCode')
            ->havingRaw('COUNT(DISTINCT ProviderAmountEach) > 1')
            ->pluck('HCPCsCode');

        $items = $codes->map(function ($code) use ($file) {
            $prices = DB::table('invoices')
                ->where('file_id', $file->id)
                ->where('HCPCsCode', $code)
                ->distinct()
                ->pluck('ProviderAmountEach')
                ->sort()
                ->values()
                ->toArray();

            return [
                'HCPCsCode' => $code,
                'price_count' => count($prices),
                //'price_variations' => $prices,
            ];
        })->sortByDesc('price_count')->values()->toArray();

        $this->storeReportItem($file, 'price_inconsistencies', $title, $items);
    }


    /** Validates if unit price × quantity equals total billed. */
    protected function checkLineItemTotals(File $file): void
    {
        $title = 'Line Item Total Mismatches';

        $items = DB::table('invoices')
            ->select(
                'id', 'OrderNumber', 'HCPCsCode', 'BilledQuantity',
                'ProviderAmountEach', 'ProviderAmountTotal',
                DB::raw('ROUND(ProviderAmountEach * BilledQuantity, 2) as expected_total'),
                DB::raw('ROUND(ProviderAmountTotal - (ProviderAmountEach * BilledQuantity), 2) as delta')
            )
            ->where('file_id', $file->id)
            ->whereRaw('ROUND(ProviderAmountEach * BilledQuantity, 2) != ROUND(ProviderAmountTotal, 2)')
            ->orderByDesc(DB::raw('ABS(ProviderAmountTotal - (ProviderAmountEach * BilledQuantity))'))
            ->get()
            ->toArray();

        $this->storeReportItem($file, 'line_total_mismatches', $title, $items);
    }

    /** Detects repeated billing for same patient, code, quantity, and date. */
    protected function checkDuplicateCharges(File $file): void
    {
        $title = 'Duplicate Charges';

        $items = DB::table('invoices')
            ->select(
                'PatientID', 'InsuredInsId', 'HCPCsCode', 'BilledQuantity', 'PaymentDate',
                DB::raw('GROUP_CONCAT(DISTINCT Description) as descriptions'),
                DB::raw('GROUP_CONCAT(DISTINCT Payee) as payees'),
                DB::raw('GROUP_CONCAT(DISTINCT OrderNumber) as order_numbers'),
                DB::raw('GROUP_CONCAT(DISTINCT PaymentNumber) as payment_numbers'),
                DB::raw('SUM(ProviderAmountTotal) as total_billed'),
                DB::raw('COUNT(*) as duplicate_count'),
                DB::raw('MIN(DOS) as first_service_date')
            )
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'InsuredInsId', 'HCPCsCode', 'BilledQuantity', 'PaymentDate')
            ->having('duplicate_count', '>', 1)
            ->orderByDesc('duplicate_count')
            ->get()
            ->map(function ($row) {
                return [
                    'PatientID' => $row->PatientID,
                    'InsuredInsId' => $row->InsuredInsId,
                    'HCPCsCode' => $row->HCPCsCode,
                    'BilledQuantity' => $row->BilledQuantity,
                    'PaymentDate' => $row->PaymentDate,
                    'descriptions' => explode(',', $row->descriptions),
                    'payees' => explode(',', $row->payees),
                    'order_numbers' => explode(',', $row->order_numbers),
                    'payment_numbers' => explode(',', $row->payment_numbers),
                    'total_billed' => '$'.number_format(round($row->total_billed, 2)),
                    'duplicate_count' => number_format($row->duplicate_count),
                    'date_of_service' => $row->first_service_date,
                ];
            })->toArray();

        $this->storeReportItem($file, 'duplicate_charges', $title, $items);
    }

    /** Flags invoice lines with unit price above a defined threshold. */
    protected function checkHighUnitPrices(File $file, float $threshold = 500.00): void
    {
        $title = 'High Unit Prices';

        $items = DB::table('invoices')
            ->select('HCPCsCode', 'Description', 'ProviderAmountEach', 'PatientID', 'OrderNumber', 'PaymentDate')
            ->where('file_id', $file->id)
            ->where('ProviderAmountEach', '>', $threshold)
            ->orderByDesc('ProviderAmountEach')
            ->get()
            ->toArray();

        $this->storeReportItem($file, 'high_unit_prices', $title, $items);
    }

    /** Flags suspiciously high item quantities. */
    protected function checkExcessiveQuantities(File $file, int $threshold = 100): void
    {
        $title = 'Suspiciously High Quantities';

        $items = DB::table('invoices')
            ->select('HCPCsCode', 'Description', 'BilledQuantity', 'PatientID', 'Payee', 'OrderNumber', 'PaymentDate')
            ->where('file_id', $file->id)
            ->where('BilledQuantity', '>', $threshold)
            ->orderByDesc('BilledQuantity')
            ->get()
            ->toArray();

        $this->storeReportItem($file, 'suspiciously_high_quantities', $title, $items);
    }

    /** Finds same-day duplicates for same patient and code. */
    protected function checkSameDayDuplicates(File $file): void
    {
        $title = 'Multiple Same-Day Charges (by Date of Service)';

        $items = DB::table('invoices')
            ->select(
                'PatientID',
                'DOS',
                DB::raw('ANY_VALUE(PaymentDate) as PaymentDate'),
                'HCPCsCode',
                DB::raw('GROUP_CONCAT(DISTINCT Description) as descriptions'),
                DB::raw('GROUP_CONCAT(DISTINCT Payee) as payees'),
                DB::raw('GROUP_CONCAT(DISTINCT OrderNumber) as order_numbers'),
                DB::raw('COUNT(*) as count')
            )
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'DOS', 'HCPCsCode')
            ->having('count', '>', 1)
            ->orderByDesc('count')
            ->get()
            ->map(function ($row) {
                return [
                    'PatientID' => $row->PatientID,
                    'DOS' => $row->DOS,
                    'PaymentDate' => $row->PaymentDate,
                    'HCPCsCode' => $row->HCPCsCode,
                    'count' => number_format($row->count),
                    //'descriptions' => explode(',', $row->descriptions),
                    'payees' => explode(',', $row->payees),
                    'order_numbers' => explode(',', $row->order_numbers),
                ];
            })->toArray();

        $this->storeReportItem($file, 'multiple_same_day_charges', $title, $items);
    }

    /** Flags codes that appear across many patients. */
    protected function checkCodeSpammingAcrossPatients(File $file, int $threshold = 30): void
    {
        $title = 'Code Used Across Many Patients';

        $items = DB::table('invoices')
            ->select(
                'HCPCsCode',
                DB::raw('GROUP_CONCAT(DISTINCT Description) as descriptions'),
                DB::raw('GROUP_CONCAT(DISTINCT Payee) as payees'),
                DB::raw('COUNT(DISTINCT PatientID) as patient_count')
            )
            ->where('file_id', $file->id)
            ->groupBy('HCPCsCode')
            ->having('patient_count', '>', $threshold)
            ->orderByDesc('patient_count')
            ->get()
            ->map(function ($row) {
                return [
                    'HCPCsCode' => $row->HCPCsCode,
                    'patient_count' => number_format($row->patient_count),
                    //'descriptions' => explode(',', $row->descriptions),
                    //'payees' => explode(',', $row->payees),
                ];
            })->toArray();

        $this->storeReportItem($file, 'same_code_multiple_patients', $title, $items);
    }

    /** Finds claims missing any payment data field. */
    protected function checkMissingPayments(File $file): void
    {
        $title = 'Missing Payment Information';

        $items = DB::table('invoices')
            ->select(
                'id', 'OrderNumber', 'PatientID', 'HCPCsCode', 'Description', 'Payee',
                'BilledQuantity', 'ProviderAmountTotal', 'PaymentDate', 'PaymentNumber', 'PaymentType'
            )
            ->where('file_id', $file->id)
            ->where(function ($query) {
                $query->whereNull('PaymentDate')
                    ->orWhereNull('PaymentNumber')
                    ->orWhereNull('PaymentType');
            })
            ->get()
            ->map(function ($row) {
                $missing = [];
                if (is_null($row->PaymentDate)) $missing[] = 'PaymentDate';
                if (is_null($row->PaymentNumber)) $missing[] = 'PaymentNumber';
                if (is_null($row->PaymentType)) $missing[] = 'PaymentType';

                return [
                    'id' => $row->id,
                    'OrderNumber' => $row->OrderNumber,
                    'PatientID' => $row->PatientID,
                    'HCPCsCode' => $row->HCPCsCode,
                    'Description' => $row->Description,
                    'Payee' => $row->Payee,
                    'BilledQuantity' => $row->BilledQuantity,
                    'ProviderAmountTotal' => $row->ProviderAmountTotal,
                    'MissingFields' => $missing,
                    'PaymentDate' => $row->PaymentDate,
                    'PaymentNumber' => $row->PaymentNumber,
                    'PaymentType' => $row->PaymentType,
                ];
            })->toArray();

        $this->storeReportItem($file, 'missing_payment_info', $title, $items);
    }

    /** Lists the 10 most frequently billed codes. */
    protected function checkCodeOveruse(File $file): void
    {
        $title = 'Most Frequently Billed Codes';

        $items = DB::table('invoices')
            ->select(
                'HCPCsCode',
                'Description',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('COUNT(DISTINCT PatientID) as unique_patients'),
                DB::raw('SUM(ProviderAmountTotal) as total_billed')
            )
            ->where('file_id', $file->id)
            ->groupBy('HCPCsCode', 'Description')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'HCPCsCode' => $row->HCPCsCode,
                    'Description' => $row->Description,
                    'usage_count' => number_format($row->usage_count),
                    'unique_patients' => number_format($row->unique_patients),
                    'total_billed' => '$'.number_format(round($row->total_billed, 2)),
                ];
            })->toArray();

        $this->storeReportItem($file, 'code_usage_frequency', $title, $items);
    }
}
