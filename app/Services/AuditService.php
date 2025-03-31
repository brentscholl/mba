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
        ini_set('memory_limit', '-1');
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

    protected function checkLineItemTotals(File $file): void
    {
        Log::info('AuditService: Checking line item totals');
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

        $chunkSize = 1000;
        $items = collect();
        $pivot = collect();

        foreach ($rows as $row) {
            $items->push([
                'audit_report_id' => $report->id,
                'data' => json_encode([
                    'Order Number' => $row->OrderNumber,
                    'HCPCs Code' => $row->HCPCsCode,
                    'Billed Quantity' => $row->BilledQuantity,
                    'Provider Amount Each' => $row->ProviderAmountEach,
                    'Provider Amount Total' => $row->ProviderAmountTotal,
                    'Expected Total' => $row->expected_total,
                    'Delta' => $row->delta,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $items->chunk($chunkSize)->each(function ($chunk) {
            DB::table('audit_report_items')->insert($chunk->toArray());
        });

        // Get the IDs of the newly inserted items
        $insertedIds = DB::table('audit_report_items')
            ->where('audit_report_id', $report->id)
            ->latest('id')
            ->limit($items->count())
            ->pluck('id')
            ->reverse()
            ->values();

        foreach ($insertedIds as $index => $itemId) {
            $pivot->push([
                'audit_report_item_id' => $itemId,
                'invoice_id' => $rows[$index]->id,
            ]);
        }

        $pivot->chunk($chunkSize)->each(function ($chunk) {
            DB::table('audit_report_item_invoice')->insert($chunk->toArray());
        });
    }

    protected function checkDuplicateCharges(File $file): void
    {
        Log::info('AuditService: Checking for duplicate charges');
        $report = $this->createReport($file, 'duplicate_charges', 'Duplicate Charges');

        $groups = DB::table('invoices')
            ->select('PatientID', 'HCPCsCode', 'PaymentDate', DB::raw('COUNT(*) as count'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'HCPCsCode', 'PaymentDate')
            ->having('count', '>', 1)
            ->get();

        $items = [];
        $pivot = [];

        foreach ($groups as $group) {
            $invoiceIds = Invoice::where('file_id', $file->id)
                ->where('PatientID', $group->PatientID)
                ->where('HCPCsCode', $group->HCPCsCode)
                ->where('PaymentDate', $group->PaymentDate)
                ->pluck('id');

            $items[] = [
                'audit_report_id' => $report->id,
                'data' => json_encode([
                    'Patient ID' => $group->PatientID,
                    'HCPCs Code' => $group->HCPCsCode,
                    'Payment Date' => $group->PaymentDate,
                    'Duplicate Count' => $group->count,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        collect($items)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_items')->insert($chunk->toArray());
        });

        $insertedIds = DB::table('audit_report_items')
            ->where('audit_report_id', $report->id)
            ->latest('id')
            ->limit(count($items))
            ->pluck('id')
            ->reverse()
            ->values();

        foreach ($groups as $index => $group) {
            $invoiceIds = Invoice::where('file_id', $file->id)
                ->where('PatientID', $group->PatientID)
                ->where('HCPCsCode', $group->HCPCsCode)
                ->where('PaymentDate', $group->PaymentDate)
                ->pluck('id');

            foreach ($invoiceIds as $invoiceId) {
                $pivot[] = [
                    'audit_report_item_id' => $insertedIds[$index],
                    'invoice_id' => $invoiceId,
                ];
            }
        }

        collect($pivot)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_item_invoice')->insert($chunk->toArray());
        });
    }


    protected function checkHighUnitPrices(File $file, float $threshold = 500): void
    {
        Log::info('AuditService: Checking high unit prices');
        $report = $this->createReport($file, 'high_unit_prices', 'High Unit Prices');

        $rows = Invoice::where('file_id', $file->id)
            ->where('ProviderAmountEach', '>', $threshold)
            ->get();

        $items = [];
        $pivot = [];

        foreach ($rows as $row) {
            $items[] = [
                'audit_report_id' => $report->id,
                'data' => json_encode([
                    'HCPCs Code' => $row->HCPCsCode,
                    'Description' => $row->Description,
                    'Unit Price' => $row->ProviderAmountEach,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        collect($items)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_items')->insert($chunk->toArray());
        });

        $insertedIds = DB::table('audit_report_items')
            ->where('audit_report_id', $report->id)
            ->latest('id')
            ->limit(count($rows))
            ->pluck('id')
            ->reverse()
            ->values();

        foreach ($insertedIds as $index => $itemId) {
            $pivot[] = [
                'audit_report_item_id' => $itemId,
                'invoice_id' => $rows[$index]->id,
            ];
        }

        collect($pivot)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_item_invoice')->insert($chunk->toArray());
        });
    }

    protected function checkExcessiveQuantities(File $file, int $threshold = 100): void
    {
        Log::info('AuditService: Checking excessive quantities');
        $report = $this->createReport($file, 'suspiciously_high_quantities', 'Suspiciously High Quantities');

        $rows = Invoice::where('file_id', $file->id)
            ->where('BilledQuantity', '>', $threshold)
            ->get();

        $items = [];
        $pivot = [];

        foreach ($rows as $row) {
            $items[] = [
                'audit_report_id' => $report->id,
                'data' => json_encode([
                    'HCPCs Code' => $row->HCPCsCode,
                    'Quantity' => $row->BilledQuantity,
                    'Description' => $row->Description,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        collect($items)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_items')->insert($chunk->toArray());
        });

        $insertedIds = DB::table('audit_report_items')
            ->where('audit_report_id', $report->id)
            ->latest('id')
            ->limit(count($rows))
            ->pluck('id')
            ->reverse()
            ->values();

        foreach ($insertedIds as $index => $itemId) {
            $pivot[] = [
                'audit_report_item_id' => $itemId,
                'invoice_id' => $rows[$index]->id,
            ];
        }

        collect($pivot)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_item_invoice')->insert($chunk->toArray());
        });
    }

    protected function checkSameDayDuplicates(File $file): void
    {
        Log::info('AuditService: Checking same-day duplicates');
        $report = $this->createReport($file, 'same_day_duplicates', 'Same-Day Duplicate Charges');

        $groups = DB::table('invoices')
            ->select('PatientID', 'HCPCsCode', 'DOS', DB::raw('COUNT(*) as count'))
            ->where('file_id', $file->id)
            ->groupBy('PatientID', 'HCPCsCode', 'DOS')
            ->having('count', '>', 1)
            ->get();

        $items = [];
        $pivot = [];

        foreach ($groups as $group) {
            $invoiceIds = Invoice::where('file_id', $file->id)
                ->where('PatientID', $group->PatientID)
                ->where('HCPCsCode', $group->HCPCsCode)
                ->where('DOS', $group->DOS)
                ->pluck('id');

            $items[] = [
                'audit_report_id' => $report->id,
                'data' => json_encode([
                    'Patient ID' => $group->PatientID,
                    'HCPCs Code' => $group->HCPCsCode,
                    'Date of Service' => $group->DOS,
                    'Count' => $group->count,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        collect($items)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_items')->insert($chunk->toArray());
        });

        $insertedIds = DB::table('audit_report_items')
            ->where('audit_report_id', $report->id)
            ->latest('id')
            ->limit(count($items))
            ->pluck('id')
            ->reverse()
            ->values();

        foreach ($groups as $index => $group) {
            $invoiceIds = Invoice::where('file_id', $file->id)
                ->where('PatientID', $group->PatientID)
                ->where('HCPCsCode', $group->HCPCsCode)
                ->where('DOS', $group->DOS)
                ->pluck('id');

            foreach ($invoiceIds as $invoiceId) {
                $pivot[] = [
                    'audit_report_item_id' => $insertedIds[$index],
                    'invoice_id' => $invoiceId,
                ];
            }
        }

        collect($pivot)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_item_invoice')->insert($chunk->toArray());
        });
    }

    protected function checkMissingPayments(File $file): void
    {
        Log::info('AuditService: Checking missing payments');
        $report = $this->createReport($file, 'missing_payments', 'Missing Payment Information');

        $rows = Invoice::where('file_id', $file->id)
            ->where(function ($q) {
                $q->whereNull('PaymentDate')
                    ->orWhereNull('PaymentNumber')
                    ->orWhereNull('PaymentType');
            })
            ->get();

        $items = [];
        $pivot = [];

        foreach ($rows as $row) {
            $missing = [];
            if (!$row->PaymentDate) $missing[] = 'PaymentDate';
            if (!$row->PaymentNumber) $missing[] = 'PaymentNumber';
            if (!$row->PaymentType) $missing[] = 'PaymentType';

            $items[] = [
                'audit_report_id' => $report->id,
                'data' => json_encode([
                    'HCPCs Code' => $row->HCPCsCode,
                    'Description' => $row->Description,
                    'Missing Fields' => $missing,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        collect($items)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_items')->insert($chunk->toArray());
        });

        $insertedIds = DB::table('audit_report_items')
            ->where('audit_report_id', $report->id)
            ->latest('id')
            ->limit(count($rows))
            ->pluck('id')
            ->reverse()
            ->values();

        foreach ($insertedIds as $index => $itemId) {
            $pivot[] = [
                'audit_report_item_id' => $itemId,
                'invoice_id' => $rows[$index]->id,
            ];
        }

        collect($pivot)->chunk(1000)->each(function ($chunk) {
            DB::table('audit_report_item_invoice')->insert($chunk->toArray());
        });
    }
}
