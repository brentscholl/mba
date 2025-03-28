<?php

namespace App\Livewire;

use App\Models\File;
use Illuminate\Support\Facades\Config;
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

        if ($file->status === 'done') {
            $this->loadAudits();
        }
    }

    public function loadAudits(): void
    {
        $this->audits = $this->file->auditReportItems()
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->key => [
                    'title' => $item->title,
                    'count' => $item->count,
                    'items' => $item->items ?? [],
                ],
            ])
            ->toArray();
    }

    public function toggleSection(string $key): void
    {
        $this->sectionLimits[$key] = ($this->sectionLimits[$key] ?? 4) + 100;
    }

    public function getTotalInvoiceCountProperty(): int
    {
        return $this->file->invoices()->count();
    }

    public function render()
    {
        $this->file = $this->file->refresh();

        if ($this->file->status === 'done' && empty($this->audits)) {
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
