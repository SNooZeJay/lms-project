<?php

namespace App\Http\Controllers\Role;

use App\Http\Controllers\Controller;
use App\Services\ProgressCalculator;
use App\Services\Reporting\OperationsReport;
use App\Support\ContinueLearning;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        private readonly OperationsReport $report,
        private readonly ProgressCalculator $progress,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('profile');

        $continueProgress = ContinueLearning::forStudent($user);

        // The panel only reads the Student's own records, so a Student never
        // sees another learner's progress on this page.
        $courses = $this->report->studentEnrollments($user);

        return view('roles.student', [
            'user' => $user,
            'continueProgress' => $continueProgress,
            'continueLesson' => $continueProgress?->lesson,
            'stats' => $this->report->forStudent($user),
            'courses' => $courses,
            'progressByCourse' => $courses
                ->mapWithKeys(fn ($enrollment): array => [
                    $enrollment->id => $this->progress->forEnrollment($enrollment),
                ]),
            'recentAttempts' => $this->report->recentStudentAttempts($user, 4),
            'recentPayments' => $this->report->studentPayments($user, 4),
        ]);
    }
}
