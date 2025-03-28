<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AuditFileJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, \Illuminate\Bus\Queueable, SerializesModels;

    public File $file;

    public function __construct(File $file)
    {
        $this->file = $file;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('AuditFileJob.php: Starting audit for file ID: ' . $this->file->id);
        $this->file->update(['status' => 'auditing']);
        app(AuditService::class)->handle($this->file);

        $this->file->update(['status' => 'done']);
    }
}
