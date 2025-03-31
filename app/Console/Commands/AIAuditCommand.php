<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\AIAuditService;
use Illuminate\Console\Command;

class AIAuditCommand extends Command
{
    protected $signature = 'audit:ai {--file= : The ID of the file to audit}';
    protected $description = 'Run the AIAuditService on a specific file (or the first one found)';

    public function handle(): int
    {
        $fileId = $this->option('file');

        $file = $fileId
            ? \App\Models\File::find($fileId)
            : \App\Models\File::first();

        if (! $file) {
            $this->error($fileId ? "File with ID {$fileId} not found." : 'No files found.');
            return Command::FAILURE;
        }

        $auditTypes = [
            'all' => 'All AI Audits',
            'unrelated_procedure_codes' => 'Unrelated Procedure Codes',
            'upcoding' => 'Potential Upcoding',
            'modifier_misuse' => 'Suspicious Modifier Usage',
            'unrealistic_frequencies' => 'Unrealistic Frequencies',
            'template_billing' => 'Template Billing',
            'dme_check' => 'Excessive DME Charges',
            'suspicious_language' => 'Suspicious Language',
        ];

        $choice = $this->choice(
            'Which AI audit would you like to run?',
            array_keys($auditTypes),
            0 // default to "all"
        );

        $this->info("Selected: {$auditTypes[$choice]}");

        $file->update(['status' => 'auditing-ai']);

        if ($choice === 'all') {
            $this->info("Deleting all previous AI audit reports...");
            $file->auditReports()->where('type', 'ai')->each->delete();

            $this->info("Dispatching all AI audit jobs...");
            app(AIAuditService::class)->handle($file);
        } else {
            $this->info("Deleting previous '{$choice}' audit report (if exists)...");
            $file->auditReports()->where('type', 'ai')->where('key', $choice)->each->delete();

            $this->info("Dispatching '{$choice}' AI audit job...");
            app(AIAuditService::class)->handleSingleAudit($file, $choice);
        }

        $this->info("✅ Done. File status will be updated once jobs complete.");
        return Command::SUCCESS;
    }
}
