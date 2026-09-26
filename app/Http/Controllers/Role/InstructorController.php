<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Reporting\OperationsReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InstructorController extends Controller
{
    public function __construct(private readonly OperationsReport $report) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('profile');

        return view('roles.instructor', [
            'user' => $user,
            'stats' => $this->report->forInstructor($user),
            'courses' => Course::query()
                ->where('instructor_id', $user->id)
                ->withCount(['enrollments as active_enrollments' => fn ($query) => $query->whereIn('status', ['active', 'completed'])])
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
