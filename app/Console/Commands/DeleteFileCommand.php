<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteFileCommand extends Command
{
    protected $signature = 'file:delete';
    protected $description = 'Interactively delete a file, its invoices, audit reports, and remove the physical file from storage';

    public function handle(): int
    {
        $files = File::orderByDesc('created_at')->get();

        if ($files->isEmpty()) {
            $this->warn('No files found.');
            return 0;
        }

        $choices = $files->map(fn($file) =>
        "{$file->id}: {$file->original_filename} ({$file->status})"
        )->toArray();

        $selected = $this->choice('Which file would you like to delete?', $choices);
        $fileId = (int) explode(':', $selected)[0];

        // Load with all needed relations
        $file = File::with(['invoices', 'auditReports.items'])->find($fileId);

        if (!$file) {
            $this->error("❌ File with ID {$fileId} not found.");
            return 1;
        }

        $this->warn("⚠️ You are about to permanently delete File #{$file->id} and all related data including the physical file.");
        if (!$this->confirm('Are you sure?')) {
            $this->info("❌ Cancelled.");
            return 0;
        }

        DB::transaction(function () use ($file) {
            // Show progress
            $totalSteps = $file->invoices->count() + $file->auditReports->sum(fn($r) => $r->items?->count() ?? 0) + 3;
            $bar = $this->output->createProgressBar($totalSteps);
            $bar->start();

            // Delete invoices
            $file->invoices->each(function ($invoice) use ($bar) {
                $invoice->delete();
                $bar->advance();
            });

            // Delete audit report items and detach pivot
            foreach ($file->auditReports as $report) {
                foreach ($report->items ?? [] as $item) {
                    $item->invoices()->detach();
                    $item->delete();
                    $bar->advance();
                }
                $report->delete();
                $bar->advance();
            }

            // Delete from disk
            $path = $file->file_dir . '/' . $file->filename;
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            $bar->advance();

            // Delete file model
            $file->delete();
            $bar->advance();

            $bar->finish();
            $this->newLine();
            $this->info("✅ Deleted File #{$file->id}, invoices, audit reports, and the file from disk.");
        });

        return 0;
    }
}
