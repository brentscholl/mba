<?php

namespace App\Http\Controllers;

use App\Models\AuditReport;
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
}
