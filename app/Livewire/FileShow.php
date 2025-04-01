<?php

namespace App\Livewire;

use App\Jobs\AIAuditJob;
use App\Jobs\AuditFileJob;
use App\Models\File;
use Livewire\Component;

class FileShow extends Component
{
    public File $file;

    public array $audits = [];
    public array $expandedSections = [];
    public array $sectionLimits = [];

    public function mount(File $file): void
    {
        $this->file = $file;

        if (in_array($file->status, ['done', 'auditing', 'auditing-ai'])) {
            $this->loadAudits();
        }
    }

    public function loadAudits(): void
    {
        $this->audits = [
            'manual' => [],
            'ai' => [],
        ];

        $reports = $this->file->auditReports()->get();

        foreach ($reports as $report) {
            $group = $report->type === 'ai' ? 'ai' : 'manual';

            // Only get first 5 items
            $items = $report->items()
                ->limit(5)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->id,
                    'data' => $item->data,
                    'reasoning' => $item->reasoning,
                ])->toArray();

            $this->audits[$group][$report->key] = [
                'id' => $report->id,
                'title' => $report->title,
                'count' => $report->items()->count(),
                'items' => $items,
            ];

            $this->sectionLimits[$report->key] = 5;
        }
    }


    public function toggleSection(string $key): void
    {
        $this->sectionLimits[$key] = ($this->sectionLimits[$key] ?? 5) + 100;
    }

    public function getTotalInvoiceCountProperty(): int
    {
        return $this->file->invoices()->count();
    }

    public function rerunAIAudit()
    {
        AIAuditJob::dispatch($this->file);
    }
    public function rerunManualAudit()
    {
        AuditFileJob::dispatch($this->file);
    }

    public function render()
    {
        $this->file = $this->file->refresh();

        if (in_array($this->file->status, ['auditing-ai', 'done']) && empty($this->audits)) {
            $this->loadAudits();
        }

        return view('livewire.file-show', [
            'file' => $this->file,
            'audits' => $this->audits,
            'expandedSections' => $this->expandedSections,
            'sectionLimits' => $this->sectionLimits,
            'totalInvoiceCount' => $this->totalInvoiceCount,
        ])->layout('layouts.app');
    }
}
