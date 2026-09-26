<?php

namespace App\Http\Controllers\Student;

use App\Actions\Enrollment\EnrollStudent;
use App\Actions\Learning\MarkLessonComplete;
use App\Actions\Learning\RecordLessonActivity;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Services\ProgressCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    public function __construct(private readonly ProgressCalculator $progress) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Enrollment::class);

        $enrollments = Enrollment::query()
            ->where('student_id', $request->user()->id)
            ->with('course.instructor:id,name')
            ->latest('activated_at')
            ->latest('id')
            ->paginate(15);

        $progressByEnrollment = [];

        foreach ($enrollments as $enrollment) {
            $progressByEnrollment[$enrollment->id] = $this->progress->forEnrollment($enrollment) + [
                'visible' => $enrollment->course !== null && $this->progress->isVisibleFor($enrollment->course),
            ];
        }

        return view('student.courses.index', [
            'enrollments' => $enrollments,
            'progressByEnrollment' => $progressByEnrollment,
        ]);
    }

    public function show(Request $request, Course $course): View
    {
        Gate::authorize('viewCourseForStudent', $course);

        $course->load([
            'instructor:id,name',
            'modules' => fn ($query) => $query
                ->where('modules.status', ContentStatus::Published)
                ->orderBy('modules.position'),
            'modules.lessons' => fn ($query) => $query
                ->where('lessons.status', ContentStatus::Published)
                ->orderBy('lessons.position'),
        ]);

        $enrollment = $this->grantingEnrollment($request, $course);

        $quizzes = $course->quizzes()
            ->where('quizzes.status', QuizStatus::Published)
            ->orderBy('quizzes.position')
            ->get();

        $attemptsByQuiz = QuizAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('status', '!=', QuizAttemptStatus::InProgress)
            ->orderByDesc('attempt_number')
            ->get()
            ->groupBy('quiz_id');

        $quizState = [];

        foreach ($quizzes as $quiz) {
            $latest = $attemptsByQuiz->get($quiz->id)?->first();

            $quizState[$quiz->id] = match (true) {
                $latest === null => 'Not attempted',
                $latest->passed === true => 'Passed',
                default => 'Not passed',
            };
        }

        return view('student.courses.show', [
            'course' => $course,
            'progress' => $this->progress->forEnrollment($enrollment),
            'showProgress' => $this->progress->isVisibleFor($course),
            'completedLessonIds' => $this->progress->completedLessonIds($enrollment),
            'quizzes' => $quizzes,
            'quizState' => $quizState,
        ]);
    }

    public function showLesson(Request $request, Course $course, Lesson $lesson, RecordLessonActivity $recordActivity): View
    {
        abort_unless($lesson->module->course_id === $course->id, 404);

        Gate::authorize('viewForStudent', $lesson);

        $enrollment = $this->grantingEnrollment($request, $course);
        $progress = $recordActivity->handle($request->user(), $enrollment, $lesson);

        $lesson->load([
            'module',
            'learningMaterials' => fn ($query) => $query->orderBy('position'),
        ]);

        return view('student.lessons.show', [
            'course' => $course,
            'lesson' => $lesson,
            'progress' => $progress,
        ]);
    }

    public function completeLesson(
        Request $request,
        Course $course,
        Lesson $lesson,
        MarkLessonComplete $markLessonComplete,
    ): RedirectResponse {
        abort_unless($lesson->module->course_id === $course->id, 404);

        Gate::authorize('completeForStudent', $lesson);

        $enrollment = $this->grantingEnrollment($request, $course);
        $markLessonComplete->handle($request->user(), $enrollment, $lesson);

        return redirect()
            ->route('student.lessons.show', [$course, $lesson])
            ->with('status', 'Lesson marked as complete.');
    }

    public function store(Request $request, Course $course, EnrollStudent $enrollStudent): RedirectResponse
    {
        // Hide an unpublished course from the student role before any message is shown.
        abort_unless($course->status === CourseStatus::Published, 404);

        Gate::authorize('create', [Enrollment::class, $course]);

        $enrollStudent->handle($request->user(), $course);

        return redirect()
            ->route('student.courses.index')
            ->with('status', 'You are enrolled. Open the course to read its published lessons.');
    }

    private function grantingEnrollment(Request $request, Course $course): Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->firstOrFail();
    }
}
