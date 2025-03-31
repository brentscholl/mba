<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\File;
use App\Models\AuditReport;
use App\Services\AuditService;

class ManualAuditCommand extends Command
{
    protected $signature = 'audit:manual {--file= : The ID of the file to audit}';
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

        $reports = AuditReport::with('items')
            ->where('file_id', $file->id)
            ->where('type', 'manual')
            ->get();

        if ($reports->isNotEmpty()) {
            $this->info("Manual audit reports found for file ID {$file->id} ({$file->original_filename})");
            $this->info('Deleting existing manual audit reports and their items...');

            foreach ($reports as $report) {
                $report->items()->each(function ($item) {
                    $item->invoices()->detach(); // Detach pivot first
                    $item->delete();
                });

                $report->delete();
            }

            $this->info('Manual audit reports and items deleted.');
        }

        $this->info("Auditing file ID {$file->id} ({$file->original_filename})...");

        $file->update(['status' => 'auditing']);

        app(AuditService::class)->handle($file);

        $this->info('✅ Audit complete.');

        return Command::SUCCESS;
    }
}
