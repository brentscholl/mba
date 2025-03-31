<?php

namespace App\Services;

use App\Models\File;
use App\Models\Invoice;
use App\Models\AuditReport;
use App\Models\AuditReportItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditService
{
    public function handle(File $file): void
    {
        Log::info('AuditService: Starting manual audits for file ID: ' . $file->id);

        $this->checkLineItemTotals($file);
        $this->checkDuplicateCharges($file);
        $this->checkHighUnitPrices($file);
        $this->checkExcessiveQuantities($file);
        $this->checkSameDayDuplicates($file);
        $this->checkMissingPayments($file);
    }

    protected function createReport(File $file, string $key, string $title): AuditReport
    {
        return AuditReport::updateOrCreate(
            ['file_id' => $file->id, 'key' => $key],
            ['title' => $title, 'type' => 'manual']
        );
    }

    protected function addItem(AuditReport $report, array $data, array $invoiceIds = []): void
    {
        if (!is_array($data)) {
            Log::error('AuditService: Expected data to be array in addItem()', ['data' => $data]);
            return;
        }

        $item = AuditReportItem::create([
            'audit_report_id' => $report->id,
            'data' => $data,
        ]);

        if (!empty($invoiceIds)) {
            $item->invoices()->syncWithoutDetaching($invoiceIds);
        }
    }


    protected function checkLineItemTotals(File $file): void
    {
        $report = $this->createReport($file, 'line_total_mismatches', 'Line Item Total Mismatches');

        $rows = DB::table('invoices')
            ->select(
                'id', 'OrderNumber', 'HCPCsCode', 'BilledQuantity', 'ProviderAmountEach',
                'ProviderAmountTotal',
                DB::raw('ROUND(ProviderAmountEach * BilledQuantity, 2) as expected_total'),
                DB::raw('ROUND(ProviderAmountTotal - (ProviderAmountEach * BilledQuantity), 2) as delta')
            )
            ->where('file_id', $file->id)
            ->whereRaw('ROUND(ProviderAmountEach * BilledQuantity, 2) != ROUND(ProviderAmountTotal, 2)')
            ->get();

        foreach ($rows as $row) {
            $this->addItem($report, [
                'Order Number' => $row->OrderNumber,
                'HCPCs Code' => $row->HCPCsCode,
                'Billed Quantity' => $row->BilledQuantity,
                'Provider Amount Each' => $row->ProviderAmountEach,
                'Provider Amount Total' => $row->ProviderAmountTotal,
                'Expected Total' => $row->expected_total,
                'Delta' => $row->delta,
            ], [$row->id]);
        }
    }

    protected function checkDuplicateCharges(File $file): void
    {
        $report = $this->createReport($file, 'duplicate_charges', 'Duplicate Charges');

        $groups = DB::table('invoices')
            ->select('PatientID', 'HCPCsCode', 'PaymentDate', DB::raw('COUNT(*) as count'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'HCPCsCode', 'PaymentDate')
            ->having('count', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $invoiceIds = Invoice::where('file_id', $file->id)
                ->where('PatientID', $group->PatientID)
                ->where('HCPCsCode', $group->HCPCsCode)
                ->where('PaymentDate', $group->PaymentDate)
                ->pluck('id')
                ->toArray();

            $this->addItem($report, [
                'Patient ID' => $group->PatientID,
                'HCPCs Code' => $group->HCPCsCode,
                'Payment Date' => $group->PaymentDate,
                'Duplicate Count' => $group->count,
            ], $invoiceIds);
        }
    }

    protected function checkHighUnitPrices(File $file, float $threshold = 500): void
    {
        $report = $this->createReport($file, 'high_unit_prices', 'High Unit Prices');

        $rows = Invoice::where('file_id', $file->id)
            ->where('ProviderAmountEach', '>', $threshold)
            ->get();

        foreach ($rows as $row) {
            $this->addItem($report, [
                'HCPCs Code' => $row->HCPCsCode,
                'Description' => $row->Description,
                'Unit Price' => $row->ProviderAmountEach,
            ], [$row->id]);
        }
    }

    protected function checkExcessiveQuantities(File $file, int $threshold = 100): void
    {
        $report = $this->createReport($file, 'suspiciously_high_quantities', 'Suspiciously High Quantities');

        $rows = Invoice::where('file_id', $file->id)
            ->where('BilledQuantity', '>', $threshold)
            ->get();

        foreach ($rows as $row) {
            $this->addItem($report, [
                'HCPCs Code' => $row->HCPCsCode,
                'Quantity' => $row->BilledQuantity,
                'Description' => $row->Description,
            ], [$row->id]);
        }
    }

    protected function checkSameDayDuplicates(File $file): void
    {
        $report = $this->createReport($file, 'same_day_duplicates', 'Same-Day Duplicate Charges');

        $groups = DB::table('invoices')
            ->select('PatientID', 'HCPCsCode', 'DOS', DB::raw('COUNT(*) as count'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'HCPCsCode', 'DOS')
            ->having('count', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $invoiceIds = Invoice::where('file_id', $file->id)
                ->where('PatientID', $group->PatientID)
                ->where('HCPCsCode', $group->HCPCsCode)
                ->where('DOS', $group->DOS)
                ->pluck('id')
                ->toArray();

            $this->addItem($report, [
                'Patient ID' => $group->PatientID,
                'HCPCs Code' => $group->HCPCsCode,
                'Date of Service' => $group->DOS,
                'Count' => $group->count,
            ], $invoiceIds);
        }
    }

    protected function checkMissingPayments(File $file): void
    {
        $report = $this->createReport($file, 'missing_payments', 'Missing Payment Information');

        $rows = Invoice::where('file_id', $file->id)
            ->where(function ($q) {
                $q->whereNull('PaymentDate')
                    ->orWhereNull('PaymentNumber')
                    ->orWhereNull('PaymentType');
            })
            ->get();

        foreach ($rows as $row) {
            $missing = [];
            if (!$row->PaymentDate) $missing[] = 'PaymentDate';
            if (!$row->PaymentNumber) $missing[] = 'PaymentNumber';
            if (!$row->PaymentType) $missing[] = 'PaymentType';

            $this->addItem($report, [
                'HCPCs Code' => $row->HCPCsCode,
                'Description' => $row->Description,
                'Missing Fields' => $missing,
            ], [$row->id]);
        }
    }
}
