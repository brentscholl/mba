<?php

namespace App\Http\Controllers;

use App\Models\AuditReport;
use App\Models\AuditReportItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AuditReportController extends Controller
{
    public function show($id)
    {
        try {
            $report = AuditReport::with('file')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return redirect()->route('dashboard')->with('error', 'Audit report not found.');
        }

        return view('audit-report-show', compact('report'));
    }


    public function showItem($reportId, $itemId)
    {
        try {
            // Load the report to ensure the item belongs to it
            $report = AuditReport::findOrFail($reportId);

            // Find the item and make sure it belongs to the report
            $item = AuditReportItem::with('invoices')
                ->where('audit_report_id', $report->id)
                ->findOrFail($itemId);
        } catch (ModelNotFoundException $e) {
            return redirect()->route('dashboard')->with('error', 'Audit report item not found.');
        }

        return view('audit-report-item-show', compact('report', 'item'));
    }

}
