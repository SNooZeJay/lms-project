<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Services\Reporting\OperationsReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdministratorController extends Controller
{
    public function __construct(private readonly OperationsReport $report) {}

    public function __invoke(Request $request): View
    {
        return view('roles.administrator', [
            'user' => $request->user()->load('profile'),
            'stats' => $this->report->forAdministrator(),
            'courseStatusCounts' => $this->report->courseStatusCounts(),
            'recentUsers' => $this->report->recentUsers(5),
            'recentEnrollments' => $this->report->recentEnrollments(5),
            'recentPayments' => $this->report->recentPayments(5),
            'recentActivity' => $this->report->recentActivity(5),
        ]);
    }
}
