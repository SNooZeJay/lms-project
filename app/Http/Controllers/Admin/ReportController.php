<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reporting\OperationsReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly OperationsReport $report) {}

    public function index(Request $request): View
    {
        return view('admin.reports.index', [
            'rows' => $this->report->enrollmentRows(),
            /*
             | The counts the report is built from, and the course-level progress
             | plan.md approves for V1. Both are read here rather than handed over
             | by the dashboard, so the report is a complete page on its own and
             | does not depend on another page having been rendered first.
             */
            /*
             | `forAdministrator` used to be passed as `stats` as well. The view
             | never rendered it, so eleven count queries were paid for on every
             | visit of this report and then discarded. It is gone, and the
             | report's query cost is pinned by a test so it cannot return as an
             | unused variable.
             */
            'totals' => $this->report->reportTotals(),
            'courseProgress' => $this->report->courseProgressRows(),
        ]);
    }
}
