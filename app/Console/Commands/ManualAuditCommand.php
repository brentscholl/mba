<?php

namespace App\Console\Commands;

use App\Services\AIAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\File;
use App\Models\AuditReport;
use App\Services\AuditService;

class ManualAuditCommand extends Command
{
    protected $signature = 'audit:manual
        {--file= : The ID of the file to audit}
        {--audit= : The specific manual audit to run (or "all")}';

    protected $description = 'Run the AuditService on a specific file (or the first one found)';

    public function handle(): int
    {
        $fileId = $this->option('file');

        $file = $fileId
            ? File::find($fileId)
            : File::first();

        if (! $file) {
            $this->error($fileId ? "File with ID {$fileId} not found." : 'No files in the database.');
            return Command::FAILURE;
        }

        $auditTypes = [
            'all' => 'All Manual Audits',
            'checkLineItemTotals' => 'Check Line Item Totals',
            'checkDuplicateCharges' => 'Check Duplicate Charges',
            'checkHighUnitPrices' => 'Check High Unit Prices',
            'checkExcessiveQuantities' => 'Check Excessive Quantities',
            'checkSameDayDuplicates' => 'Check Same Day Duplicates',
            'checkMissingPayments' => 'Check Missing Payments',
        ];

        $choice = $this->option('audit');

        if (! $choice || ! array_key_exists($choice, $auditTypes)) {
            $choice = $this->choice(
                'Which manual audit would you like to run?',
                array_keys($auditTypes),
                0 // default to "all"
            );
        }

        $this->info("Selected: {$auditTypes[$choice]}");

        if ($choice === 'all') {
            $this->info("Deleting all previous manual audit reports...");

            $reports = AuditReport::with('items')
                ->where('file_id', $file->id)
                ->where('type', 'manual')
                ->get();

            if ($reports->isNotEmpty()) {
                $this->line("Manual audit reports found for file ID {$file->id} ({$file->original_filename})");
                $this->line('Deleting existing manual audit reports and their items...');

                foreach ($reports as $report) {
                    $report->items()
                        ->select('id')
                        ->chunkById(1000, function ($items) {
                            $itemIds = $items->pluck('id');

                            DB::table('audit_report_item_invoice')
                                ->whereIn('audit_report_item_id', $itemIds)
                                ->delete();

                            DB::table('audit_report_items')
                                ->whereIn('id', $itemIds)
                                ->delete();
                        });

                    $report->delete();
                }

                $this->line('Manual audit reports and items deleted.');
            }

            $this->line("Auditing file ID {$file->id} ({$file->original_filename})...");
            $file->update(['status' => 'auditing']);
            app(AuditService::class)->handle($file);
        } else {
            $this->info("Deleting previous '{$choice}' audit report (if exists)...");

            $reports = AuditReport::with('items')
                ->where('file_id', $file->id)
                ->where('type', 'manual')
                ->where('key', $choice)
                ->get();

            if ($reports->isNotEmpty()) {
                $this->line("Manual audit reports found for file ID {$file->id} ({$file->original_filename})");
                $this->line('Deleting existing manual audit reports and their items...');

                foreach ($reports as $report) {
                    $report->items()
                        ->select('id')
                        ->chunkById(1000, function ($items) {
                            $itemIds = $items->pluck('id');

                            DB::table('audit_report_item_invoice')
                                ->whereIn('audit_report_item_id', $itemIds)
                                ->delete();

                            DB::table('audit_report_items')
                                ->whereIn('id', $itemIds)
                                ->delete();
                        });

                    $report->delete();
                }

                $this->line('Manual audit reports and items deleted.');
            }

            $this->info("Dispatching '{$choice}' Manual audit job...");
            app(AuditService::class)->handleSingleAudit($file, $choice);
        }

        $file->update(['status' => 'done']);

        $this->info('Audit complete.');
        return Command::SUCCESS;
    }
}
