<?php

namespace App\Livewire;

use App\Models\AuditReport;
use Livewire\Component;
use Livewire\WithPagination;

class AuditReportItems extends Component
{
    use WithPagination;

    public AuditReport $report;

    public function render()
    {
        $items = $this->report->items()->with('invoices')->paginate(50);

        return view('livewire.audit-report-items', [
            'items' => $items,
        ]);
    }
}
