<?php

namespace App\Http\Controllers\Student;

use App\Actions\Enrollment\EnrollStudent;
use App\Actions\Learning\MarkLessonComplete;
use App\Actions\Learning\RecordLessonActivity;
use App\Enums\ContentStatus;
use App\Enums\ConversationKind;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\QuizAttempt;
use App\Services\Learning\CourseCompletionChecker;
use App\Services\ProgressCalculator;
use App\Support\StudentPaymentState;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly ProgressCalculator $progress,
        private readonly CourseCompletionChecker $completionChecker,
    ) {}

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
        $paymentStates = [];

        // The latest payment per enrollment, so the list can say whether money
        // landed instead of showing a raw status the Student cannot act on.
        $paymentsByEnrollment = Payment::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->latest('id')
            ->get()
            ->groupBy('enrollment_id')
            ->map(fn ($group) => $group->first());

        foreach ($enrollments as $enrollment) {
            $progressByEnrollment[$enrollment->id] = $this->progress->forEnrollment($enrollment) + [
                'visible' => $enrollment->course !== null && $this->progress->isVisibleFor($enrollment->course),
            ];

            $paymentStates[$enrollment->id] = StudentPaymentState::for(
                $enrollment,
                $paymentsByEnrollment->get($enrollment->id),
                $enrollment->course,
            );
        }

        return view('student.courses.index', [
            'enrollments' => $enrollments,
            'progressByEnrollment' => $progressByEnrollment,
            'paymentStates' => $paymentStates,
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

        $completion = $this->completionChecker->check($enrollment);

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

            // These are the stored state keys the interface reads, so the page
            // renders one consistent sentence per state instead of a sentence
            // written here.
            $quizState[$quiz->id] = match (true) {
                $latest === null => 'not_attempted',
                $latest->passed === true => 'passed',
                default => 'not_passed',
            };
        }

        /*
         | The course thread, if this Student already has one with this course's
         | instructor.
         |
         | The route that opens it has existed since direct messaging was approved,
         | and no page posted to it, so a Student could read the support desk and
         | reply inside a thread but could never open one with the person teaching
         | the course they are taking. That is the conversation the plan describes
         | first, and it was the one with no way in.
         |
         | It is read here rather than in the view so the page can offer one of two
         | things and never both: the button to open a thread, or a link to the
         | thread that already exists. Rendering the button when a thread is already
         | open would tell a Student to start a conversation they are already in.
         |
         | Found by requester and course rather than through the participant scope,
         | because this is the one thread this Student asked for, and the pair is
         | unique by index. The instructor is the other participant either way.
         */
        $courseThread = Conversation::query()
            ->where('kind', ConversationKind::Course)
            ->where('course_id', $course->id)
            ->where('requester_id', $request->user()->id)
            ->first();

        return view('student.courses.show', [
            'course' => $course,
            'enrollment' => $enrollment,
            'progress' => $this->progress->forEnrollment($enrollment),
            'showProgress' => $this->progress->isVisibleFor($course),
            'completedLessonIds' => $this->progress->completedLessonIds($enrollment),
            'quizzes' => $quizzes,
            'quizState' => $quizState,
            'completion' => $completion,
            'certificate' => $enrollment->certificate()->first(),
            'courseThread' => $courseThread,
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

        $enrollment = $enrollStudent->handle($request->user(), $course);

        // A paid enrollment starts life as pending_payment and grants nothing.
        // Telling the Student they are enrolled would be false, and the courses
        // list offers no way to pay, so the checkout continues the flow.
        if ($enrollment->status === EnrollmentStatus::PendingPayment) {
            return redirect()->route('student.payments.checkout', [$course]);
        }

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
