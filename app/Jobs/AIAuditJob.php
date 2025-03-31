<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\AIAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AIAuditJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, \Illuminate\Bus\Queueable, SerializesModels;

    public $timeout = 43200; // 12 hours

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
        Log::info('AIAuditFileJob.php: Starting AI audit for file ID: ' . $this->file->id);
        $this->file->update(['status' => 'auditing-ai']);
        app(AIAuditService::class)->handle($this->file);
    }
}
