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
        $statusCounts = $this->report->enrollmentStatusCounts();

        // The labels are the plain words a person would use. The raw state name
        // is never shown to an Administrator, because it is a storage detail.
        $stateLabels = [
            'pending_payment' => 'Awaiting payment',
            'active' => 'In progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        $stateRows = [];

        foreach ($statusCounts as $state => $count) {
            $stateRows[] = [
                'label' => $stateLabels[$state] ?? $state,
                'value' => $count,
                'tone' => $state === 'completed' ? 'accent' : 'primary',
            ];
        }

        return view('roles.administrator', [
            'user' => $request->user()->load('profile'),
            'stats' => $this->report->forAdministrator(),
            'courseStatusCounts' => $this->report->courseStatusCounts(),
            'enrollmentStatusCounts' => $statusCounts,
            'enrollmentRows' => $stateRows,
            'agenda' => $this->report->administratorAgenda(6),
            'recentUsers' => $this->report->recentUsers(5),
            'recentEnrollments' => $this->report->recentEnrollments(5),
            'recentPayments' => $this->report->recentPayments(5),
            'recentActivity' => $this->report->recentActivity(5),
        ]);
    }
}
