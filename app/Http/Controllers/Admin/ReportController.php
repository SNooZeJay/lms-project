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
            'stats' => $this->report->forAdministrator(),
        ]);
    }
}
