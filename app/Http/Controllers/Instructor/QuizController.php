<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Quizzes\CreateQuizQuestion;
use App\Actions\Quizzes\ManageQuiz;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quizzes\StoreQuizQuestionRequest;
use App\Http\Requests\Quizzes\StoreQuizRequest;
use App\Http\Requests\Quizzes\UpdateQuizRequest;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuizController extends Controller
{
    public function store(StoreQuizRequest $request, Course $course, ManageQuiz $manageQuiz): RedirectResponse
    {
        $manageQuiz->create($request->user(), $course, $request->safe()->all());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Quiz added as a private draft.');
    }

    public function update(UpdateQuizRequest $request, Course $course, Quiz $quiz, ManageQuiz $manageQuiz): RedirectResponse
    {
        abort_unless($quiz->course_id === $course->id, 404);
        $manageQuiz->update($request->user(), $quiz, $request->safe()->all());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Quiz updated.');
    }

    public function publish(Request $request, Course $course, Quiz $quiz, ManageQuiz $manageQuiz): RedirectResponse
    {
        abort_unless($quiz->course_id === $course->id, 404);
        Gate::authorize('update', $quiz);
        $manageQuiz->publish($request->user(), $quiz);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Quiz published. Students in this course can now take it.');
    }

    public function archive(Request $request, Course $course, Quiz $quiz, ManageQuiz $manageQuiz): RedirectResponse
    {
        abort_unless($quiz->course_id === $course->id, 404);
        Gate::authorize('archive', $quiz);
        $manageQuiz->archive($request->user(), $quiz);

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Quiz archived. Existing attempts are kept.');
    }

    public function storeQuestion(
        StoreQuizQuestionRequest $request,
        Course $course,
        Quiz $quiz,
        CreateQuizQuestion $createQuizQuestion,
    ): RedirectResponse {
        abort_unless($quiz->course_id === $course->id, 404);
        Gate::authorize('update', $quiz);
        $createQuizQuestion->handle($request->user(), $quiz, $request->safe()->all());

        return redirect()
            ->route('instructor.courses.show', $course)
            ->with('status', 'Question added.');
    }
}
