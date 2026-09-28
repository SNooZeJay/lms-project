<?php

namespace App\Http\Controllers\Role;

use App\Enums\CourseStatus;
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

        // Every query below is scoped to the signed in Instructor, so another
        // Instructor's courses, students, and results are never loaded.
        $attention = $this->report->studentsNeedingAttention($user);

        $courses = Course::query()
            ->where('instructor_id', $user->id)
            ->withCount(['enrollments as active_enrollments' => fn ($query) => $query->whereIn('status', ['active', 'completed'])])
            ->latest('id')
            ->limit(5)
            ->get();

        // The chart is built from the enrollment count already loaded above, so
        // it adds no query. It answers "where are my students", which is the one
        // question a course list on its own cannot.
        $enrollmentRows = $courses
            ->map(fn (Course $course): array => [
                'label' => $course->title,
                'value' => (int) $course->active_enrollments,
                'href' => route('instructor.courses.show', $course),
                'tone' => $course->status === CourseStatus::Published ? 'accent' : 'primary',
            ])
            ->values()
            ->all();

        return view('roles.instructor', [
            'user' => $user,
            'stats' => $this->report->forInstructor($user),
            'courses' => $courses,
            'enrollmentRows' => $enrollmentRows,
            'agenda' => $this->report->instructorAgenda($user, 6),
            'needsAttention' => $attention['enrollments'],
            'lessonsRemaining' => $attention['lessons'],
            'recentResults' => $this->report->recentQuizResults($user, 5),
        ]);
    }
}
