<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\File;
use App\Services\AuditService;

class AuditFile extends Command
{
    protected $signature = 'audit:file {--file= : The ID of the file to audit}';
    protected $description = 'Run the CsvAuditService on a specific file (or the first one found)';

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

        if($file->auditReportItems()->exists()) {
            $this->info("Audit Report Items found for ID: {$file->id} ({$file->original_filename})...");
            $this->info('Deleting existing audit report items...');
            // Delete the existing audit report items
            $file->auditReportItems()->delete();

            $this->info('Existing audit report items deleted.');
        }

        $this->info("Auditing file with ID: {$file->id} ({$file->original_filename})...");

        $file->update(['status' => 'auditing']);
        app(AuditService::class)->handle($file);
        $file->update(['status' => 'done']);

        $this->info('✅ Audit complete.');
        return Command::SUCCESS;
    }
}
