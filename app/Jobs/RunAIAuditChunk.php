<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\Invoice;
use App\Models\AuditReport;
use App\Models\AuditReportItem;
use App\Services\AIAuditService;
use App\Services\OpenAIService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\RateLimitedMiddleware\RateLimited;

class RunAIAuditChunk implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $tries = 5;
    public int $backoff = 60;

    public function __construct(
        public File $file,
        public string $auditKey,
        public string $auditTitle,
        public string $auditType,
        public array $chunk
    ) {}

    public function middleware(): array
    {
        return [
            (new RateLimited('openai'))
                ->allow(480)
                ->everySeconds(60)
                ->releaseAfterSeconds(5),
        ];
    }

    public function tags(): array
    {
        return [
            'file:' . $this->file->id,
            'audit:' . $this->auditKey,
        ];
    }

    public function handle(OpenAIService $ai): void
    {
        try {
            $service = app(AIAuditService::class);
            $prompt = $service->buildPrompt($this->auditType, $this->chunk);
            $response = $ai->ask($prompt);

            if (!is_array($response)) {
                Log::warning("AI response was not valid JSON.");
                return;
            }

            $report = AuditReport::firstOrCreate([
                'file_id' => $this->file->id,
                'key' => $this->auditKey,
            ], [
                'title' => $this->auditTitle,
                'type' => 'ai',
            ]);

            // Normalize to an array of results
            $responseItems = $this->normalizeResponse($response);

            foreach ($responseItems as $p) {
                $result = $service->transformResult(
                    auditType: $this->auditType,
                    p: $p,
                    parsed: $responseItems,
                    raw: json_encode($responseItems),
                );

                if (!$result) {
                    continue;
                }

                $item = new AuditReportItem([
                    'audit_report_id' => $report->id,
                    'data' => $result['data'],
                ]);
                $item->save();

                if (!empty($result['invoice_ids'])) {
                    $invoiceIds = Invoice::where('file_id', $this->file->id)
                        ->whereIn('id', $result['invoice_ids'])
                        ->pluck('id');

                    $item->invoices()->sync($invoiceIds);
                }
            }

            Log::info("✅ Stored AI results for '{$this->auditKey}' (chunk size: " . count($this->chunk) . ")");
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Rate limit')) {
                Log::warning("Rate limit hit, releasing job back to queue...");
                $this->release(60);
                return;
            }

            Log::error("AI audit failed for audit [{$this->auditKey}]: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Normalize the AI response into an array of patient-level records.
     */
    private function normalizeResponse(array $response): array
    {
        return $this->isAssoc($response) ? [$response] : $response;
    }

    /**
     * Determine if an array is associative.
     */
    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
