<?php

namespace App\Http\Controllers\Student;

use App\Actions\Enrollment\EnrollStudent;
use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Enrollment::class);

        $enrollments = Enrollment::query()
            ->where('student_id', $request->user()->id)
            ->with('course.instructor:id,name')
            ->latest('activated_at')
            ->latest('id')
            ->paginate(15);

        return view('student.courses.index', [
            'enrollments' => $enrollments,
        ]);
    }

    public function store(Request $request, Course $course, EnrollStudent $enrollStudent): RedirectResponse
    {
        // Hide an unpublished course from the student role before any message is shown.
        abort_unless($course->status === CourseStatus::Published, 404);

        Gate::authorize('create', [Enrollment::class, $course]);

        $enrollStudent->handle($request->user(), $course);

        return redirect()
            ->route('student.courses.index')
            ->with('status', 'You are enrolled. Lesson content opens in a later release.');
    }
}
