<?php
namespace App\Jobs;

use App\Models\File;
use App\Services\AuditService;
use App\Services\CsvExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCsvUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public File $file;

    public function __construct(File $file)
    {
        $this->file = $file;
    }

    public function handle(): void
    {
        Log::info('ProcessCsvUpload.php: Starting CSV processing for file ID: ' . $this->file->id);
        $this->file->update(['status' => 'extracting']);
        app(CsvExtractionService::class)->handle($this->file);

        Log::info('ProcessCsvUpload.php: Dispatching AuditFileJob for file ID: ' . $this->file->id);
        AuditFileJob::dispatch($this->file);
    }

}
