<?php

namespace App\Http\Controllers\Student;

use App\Actions\Quizzes\StartQuizAttempt;
use App\Actions\Quizzes\SubmitQuizAttempt;
use App\Enums\QuizAttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quizzes\SubmitQuizAttemptRequest;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Quizzes\QuizGrader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class QuizController extends Controller
{
    public function __construct(private readonly QuizGrader $grader) {}

    public function show(Course $course, Quiz $quiz): View
    {
        Gate::authorize('viewForStudent', $quiz);

        abort_unless($quiz->course_id === $course->id, 404);

        $attempts = $quiz->attempts()
            ->where('student_id', auth()->id())
            ->orderByDesc('attempt_number')
            ->get();

        return view('student.quizzes.show', [
            'course' => $course,
            'quiz' => $quiz,
            'questions' => $this->grader->studentProjection($quiz),
            'attempts' => $attempts,
            'attemptsUsed' => $attempts->count(),
            'maxAttempts' => (int) $quiz->max_attempts,
            'hasPassed' => $attempts->contains(fn (QuizAttempt $attempt) => $attempt->passed),
            'openAttempt' => $attempts->firstWhere('status', QuizAttemptStatus::InProgress),
        ]);
    }

    public function start(
        Course $course,
        Quiz $quiz,
        StartQuizAttempt $startQuizAttempt,
    ): RedirectResponse {
        abort_unless($quiz->course_id === $course->id, 404);
        Gate::authorize('startForStudent', $quiz);

        $attempt = $startQuizAttempt->handle(request()->user(), $quiz);

        return redirect()
            ->route('student.quizzes.attempts.show', [$course, $quiz, $attempt]);
    }

    public function attempt(Course $course, Quiz $quiz, QuizAttempt $attempt): View
    {
        abort_unless($quiz->course_id === $course->id, 404);
        Gate::authorize('viewAttemptForStudent', $attempt);

        abort_unless($attempt->quiz_id === $quiz->id, 404);
        abort_if($attempt->status !== QuizAttemptStatus::InProgress, 404);

        $questions = $this->grader->studentProjection($quiz);

        $selected = $attempt->answers->pluck('selected_option_id', 'question_id');

        return view('student.quizzes.attempt', [
            'course' => $course,
            'quiz' => $quiz,
            'attempt' => $attempt,
            'questions' => $questions,
            'selected' => $selected,
        ]);
    }

    public function submit(
        SubmitQuizAttemptRequest $request,
        Course $course,
        Quiz $quiz,
        QuizAttempt $attempt,
        SubmitQuizAttempt $submitQuizAttempt,
    ): RedirectResponse {
        $submitQuizAttempt->handle(
            $request->user(),
            $quiz,
            $attempt,
            $request->selections(),
        );

        return redirect()
            ->route('student.quizzes.attempts.result', [$course, $quiz, $attempt]);
    }

    public function result(Course $course, Quiz $quiz, QuizAttempt $attempt): View
    {
        abort_unless($quiz->course_id === $course->id, 404);
        Gate::authorize('viewAttemptForStudent', $attempt);

        abort_unless($attempt->quiz_id === $quiz->id, 404);
        abort_if($attempt->status === QuizAttemptStatus::InProgress, 404);

        return view('student.quizzes.result', [
            'course' => $course,
            'quiz' => $quiz,
            'attempt' => $attempt,
            'questions' => $quiz->questions()->with('options')->get(),
            'answers' => $attempt->answers()->with('selectedOption')->get()->keyBy('question_id'),
        ]);
    }
}
