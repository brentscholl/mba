<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteFileCommand extends Command
{
    protected $signature = 'file:delete {file_id?}';
    protected $description = 'Delete a file and its related invoices and audit reports';

    public function handle(): int
    {
        // If no file ID passed, let user choose interactively
        $fileId = $this->argument('file_id');

        if (!$fileId) {
            $fileId = $this->choice(
                'Select a file to delete',
                File::latest()->take(20)->get()->mapWithKeys(fn($f) => [$f->id => "[#{$f->id}] {$f->original_filename}"])->toArray()
            );
        }

        $file = File::with(['auditReports'])->find($fileId);

        if (!$file) {
            $this->error("❌ File with ID {$fileId} not found.");
            return 1;
        }

        $this->warn("⚠️ You are about to delete File #{$file->id}: {$file->original_filename} and all related data.");
        if (!$this->confirm('Are you sure?')) {
            $this->info("❌ Cancelled.");
            return 0;
        }

        DB::transaction(function () use ($file) {
            $this->info('🧹 Deleting invoices...');
            $invoiceCount = $file->invoices()->count();
            $file->invoices()->delete();

            $this->info('🧹 Deleting audit reports and items...');
            foreach ($file->auditReports as $report) {
                $itemIds = $report->auditReportItems()->pluck('id');

                // Bulk delete pivot entries
                DB::table('audit_report_item_invoice')
                    ->whereIn('audit_report_item_id', $itemIds)
                    ->delete();

                // Delete items in chunks
                $report->auditReportItems()
                    ->select('id')
                    ->chunkById(1000, function ($items) {
                        DB::table('audit_report_items')
                            ->whereIn('id', $items->pluck('id'))
                            ->delete();
                    });

                // Delete the report
                $report->delete();
            }

            // Delete the physical file from storage
            if ($file->file_dir && $file->filename) {
                $path = $file->file_dir . '/' . $file->filename;
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    $this->info("🗑 Deleted file from storage: {$path}");
                } else {
                    $this->warn("⚠️ File not found in storage: {$path}");
                }
            }

            // Delete the file record itself
            $file->delete();

            $this->info("✅ Deleted File #{$file->id}, {$invoiceCount} invoices, and related audit reports.");
        });

        return 0;
    }
}
