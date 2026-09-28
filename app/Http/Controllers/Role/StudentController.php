<?php

namespace App\Http\Controllers\Role;

use App\Enums\EnrollmentStatus;
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

        $stats = $this->report->forStudent($user);

        // The mean is taken over the same enrollments the progress list is built
        // from, so the headline and the bars below it can never disagree.
        $stats['average_progress'] = $this->report->averageStudentProgress($user);
        $stats['quizzes_pending'] = $this->report->pendingQuizCount($user);

        // Calculated once in a single batched read and reused by the course list
        // and the chart, so the two can never disagree and the progress is not
        // calculated once per course. Reading it per enrollment made this the
        // most expensive part of the page for a student with many courses.
        $progressByCourse = $this->progress->forEnrollments($courses);

        // One bar per enrolled course, from that same progress, so the chart
        // costs no extra query.
        $progressRows = $courses
            ->map(fn ($enrollment): array => [
                'label' => $enrollment->course->title,
                'value' => (int) ($progressByCourse[$enrollment->id]['percentage'] ?? 0),
                'max' => 100,
                'href' => route('student.courses.show', $enrollment->course_id),
                'tone' => $enrollment->status === EnrollmentStatus::Completed ? 'accent' : 'primary',
            ])
            ->values()
            ->all();

        return view('roles.student', [
            'user' => $user,
            'continueProgress' => $continueProgress,
            'continueLesson' => $continueProgress?->lesson,
            'stats' => $stats,
            'courses' => $courses,
            'progressByCourse' => $progressByCourse,
            'progressRows' => $progressRows,
            'agenda' => $this->report->studentAgenda($user, 6),
            'recentAttempts' => $this->report->recentStudentAttempts($user, 4),
            'recentPayments' => $this->report->studentPayments($user, 4),
        ]);
    }
}
