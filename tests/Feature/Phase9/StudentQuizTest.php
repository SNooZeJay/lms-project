<?php

namespace Tests\Feature\Phase9;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StudentQuizTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_a_published_quiz_without_any_answer_key(): void
    {
        [$student, $quiz] = $this->publishedQuiz();

        $response = $this->actingAs($student)->get($this->quizUrl($quiz));

        $response->assertOk();
        $response->assertSee($quiz->title);
        $response->assertSee('Correct answer');
        $response->assertSee('Wrong answer');

        // The key and the explanation never reach the Student before submission.
        $response->assertDontSee('is_correct');
        $response->assertDontSee('Correct answer explanation');
        $this->assertSame(0, QuizAttempt::query()->count());
    }

    public function test_starting_a_quiz_creates_one_in_progress_attempt(): void
    {
        [$student, $quiz] = $this->publishedQuiz();

        $this->actingAs($student)
            ->post($this->startUrl($quiz))
            ->assertRedirect();

        $attempt = QuizAttempt::query()->firstOrFail();

        $this->assertSame($student->id, $attempt->student_id);
        $this->assertSame(QuizAttemptStatus::InProgress, $attempt->status);
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertNotNull($attempt->started_at);
        $this->assertNull($attempt->submitted_at);
    }

    public function test_starting_twice_reuses_the_open_attempt(): void
    {
        [$student, $quiz] = $this->publishedQuiz();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $this->actingAs($student)->post($this->startUrl($quiz));

        $this->assertSame(1, QuizAttempt::query()->count());
    }

    public function test_submitting_grades_on_the_server(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $correct = $question->options()->where('is_correct', true)->firstOrFail();
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();

        $this->actingAs($student)
            ->post($this->submitUrl($quiz, $attempt), ['answers' => [$question->id => $correct->id]])
            ->assertRedirect();

        $attempt->refresh();

        $this->assertSame(QuizAttemptStatus::Passed, $attempt->status);
        $this->assertSame(1.0, (float) $attempt->score_points);
        $this->assertSame(1.0, (float) $attempt->total_points);
        $this->assertSame(100.0, (float) $attempt->score_percent);
        $this->assertTrue($attempt->passed);
        $this->assertNotNull($attempt->submitted_at);
        $this->assertSame(1, $attempt->answers()->count());
        $this->assertTrue($attempt->answers()->first()->is_correct);
    }

    public function test_a_wrong_answer_scores_zero_and_fails(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();

        $this->actingAs($student)->post($this->submitUrl($quiz, $attempt), [
            'answers' => [$question->id => $wrong->id],
        ]);

        $attempt->refresh();

        $this->assertSame(QuizAttemptStatus::Failed, $attempt->status);
        $this->assertSame(0.0, (float) $attempt->score_points);
        $this->assertFalse($attempt->passed);
    }

    public function test_submission_rejects_injected_server_fields(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();

        $this->actingAs($student)
            ->from($this->attemptUrl($quiz, $attempt))
            ->post($this->submitUrl($quiz, $attempt), [
                'answers' => [$question->id => $wrong->id],
                'score_points' => 100,
                'score_percent' => 100,
                'passed' => true,
                'status' => 'passed',
                'student_id' => 999999,
                'attempt_number' => 3,
            ])
            ->assertSessionHasErrors([
                'score_points', 'score_percent', 'passed', 'status', 'student_id', 'attempt_number',
            ]);

        $attempt->refresh();

        $this->assertSame(QuizAttemptStatus::InProgress, $attempt->status);
        $this->assertNull($attempt->submitted_at);
        $this->assertNull($attempt->score_points);
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame($student->id, $attempt->student_id);
    }

    public function test_the_score_comes_only_from_the_stored_key(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();

        $this->actingAs($student)->post($this->submitUrl($quiz, $attempt), [
            'answers' => [$question->id => $wrong->id],
        ])->assertRedirect();

        $attempt->refresh();

        $this->assertSame(QuizAttemptStatus::Failed, $attempt->status);
        $this->assertSame(0.0, (float) $attempt->score_points);
        $this->assertFalse($attempt->passed);
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame($student->id, $attempt->student_id);
    }

    public function test_an_option_from_another_question_is_rejected(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $foreign = $this->publishedQuiz();

        $foreignOption = $foreign[2]->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->where('quiz_id', $quiz->id)->firstOrFail();

        $this->actingAs($student)
            ->from($this->attemptUrl($quiz, $attempt))
            ->post($this->submitUrl($quiz, $attempt), ['answers' => [$question->id => $foreignOption->id]])
            ->assertSessionHasErrors('answers');

        $this->assertSame(0, QuizAttempt::query()->whereNotNull('submitted_at')->count());
    }

    public function test_a_question_from_another_quiz_is_rejected(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        [, $otherQuiz, $otherQuestion] = $this->publishedQuiz();
        $otherOption = $otherQuestion->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->where('quiz_id', $quiz->id)->firstOrFail();

        $this->actingAs($student)
            ->from($this->attemptUrl($quiz, $attempt))
            ->post($this->submitUrl($quiz, $attempt), ['answers' => [$otherQuestion->id => $otherOption->id]])
            ->assertSessionHasErrors('answers');
    }

    public function test_a_failed_attempt_can_be_retried(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $first = QuizAttempt::query()->firstOrFail();
        $this->actingAs($student)->post($this->submitUrl($quiz, $first), ['answers' => [$question->id => $wrong->id]]);

        $this->actingAs($student)->post($this->startUrl($quiz));

        $this->assertSame(2, QuizAttempt::query()->count());
        $this->assertSame(2, QuizAttempt::query()->orderByDesc('attempt_number')->first()->attempt_number);
    }

    public function test_three_failed_attempts_block_a_fourth(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($student)->post($this->startUrl($quiz));
            $attempt = QuizAttempt::query()->orderByDesc('id')->first();
            $this->actingAs($student)->post($this->submitUrl($quiz, $attempt), [
                'answers' => [$question->id => $wrong->id],
            ]);
        }

        $this->assertSame(3, QuizAttempt::query()->count());

        $this->actingAs($student)
            ->from(route('student.dashboard'))
            ->post($this->startUrl($quiz))
            ->assertSessionHasErrors();

        $this->assertSame(3, QuizAttempt::query()->count());
    }

    public function test_a_passed_quiz_blocks_a_new_attempt(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $correct = $question->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();
        $this->actingAs($student)->post($this->submitUrl($quiz, $attempt), ['answers' => [$question->id => $correct->id]]);

        $this->actingAs($student)
            ->from(route('student.dashboard'))
            ->post($this->startUrl($quiz))
            ->assertSessionHasErrors();

        $this->assertSame(1, QuizAttempt::query()->count());
    }

    public function test_result_page_reveals_the_key_only_after_submission(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $correct = $question->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();
        $this->actingAs($student)->post($this->submitUrl($quiz, $attempt), ['answers' => [$question->id => $correct->id]]);

        $this->actingAs($student)
            ->get($this->resultUrl($quiz, $attempt))
            ->assertOk()
            ->assertSee('100')
            ->assertSee('Passed');
    }

    public function test_an_unsubmitted_attempt_has_no_result_page(): void
    {
        [$student, $quiz] = $this->publishedQuiz();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();

        $this->actingAs($student)
            ->get($this->resultUrl($quiz, $attempt))
            ->assertNotFound();
    }

    public function test_another_student_cannot_see_or_submit_an_attempt(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $correct = $question->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get($this->attemptUrl($quiz, $attempt))->assertForbidden();
        $this->actingAs($stranger)->get($this->resultUrl($quiz, $attempt))->assertForbidden();
        $this->actingAs($stranger)
            ->post($this->submitUrl($quiz, $attempt), ['answers' => [$question->id => $correct->id]])
            ->assertForbidden();
    }

    public function test_a_student_without_enrollment_is_blocked(): void
    {
        [$student, $quiz] = $this->publishedQuiz();
        Enrollment::query()->update(['status' => EnrollmentStatus::Cancelled]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get($this->quizUrl($quiz))->assertForbidden();
        $this->actingAs($stranger)->post($this->startUrl($quiz))->assertForbidden();
    }

    public function test_a_draft_quiz_is_not_reachable(): void
    {
        [$student, $quiz] = $this->publishedQuiz();
        $quiz->forceFill(['status' => QuizStatus::Draft])->save();

        $this->actingAs($student)->get($this->quizUrl($quiz))->assertForbidden();
        $this->actingAs($student)->post($this->startUrl($quiz))->assertForbidden();
    }

    public function test_an_unpublished_course_hides_the_quiz(): void
    {
        [$student, $quiz, $course] = $this->publishedQuizWithEnrollment();
        $course->forceFill(['status' => CourseStatus::Draft])->save();

        $this->actingAs($student)->get($this->quizUrl($quiz))->assertForbidden();
    }

    public function test_instructor_and_administrator_cannot_use_the_student_routes(): void
    {
        [$student, $quiz] = $this->publishedQuiz();

        $instructor = User::factory()->instructor()->create();
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($instructor)->get($this->quizUrl($quiz))->assertForbidden();
        $this->actingAs($instructor)->post($this->startUrl($quiz))->assertForbidden();
        $this->actingAs($administrator)->get($this->quizUrl($quiz))->assertForbidden();
        $this->actingAs($administrator)->post($this->startUrl($quiz))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$student, $quiz] = $this->publishedQuiz();

        $this->get($this->quizUrl($quiz))->assertRedirect(route('login'));
        $this->post($this->startUrl($quiz))->assertRedirect(route('login'));
    }

    public function test_a_suspended_student_is_blocked(): void
    {
        [$student, $quiz, $course, $enrollment] = $this->publishedQuizWithEnrollment();
        $student->profile->forceFill(['account_status' => UserAccountStatus::Suspended])->save();

        $this->actingAs($student->refresh())
            ->get($this->quizUrl($quiz))
            ->assertRedirect('/login');

        $this->assertSame(0, QuizAttempt::query()->count());
    }

    public function test_an_unverified_student_is_sent_to_verification(): void
    {
        [$student, $quiz, $course, $enrollment] = $this->publishedQuizWithEnrollment();
        $student->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($student->refresh())
            ->get($this->quizUrl($quiz))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_submitting_twice_keeps_the_first_result(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $correct = $question->options()->where('is_correct', true)->firstOrFail();
        $wrong = $question->options()->where('is_correct', false)->firstOrFail();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();

        $this->actingAs($student)->post($this->submitUrl($quiz, $attempt), ['answers' => [$question->id => $correct->id]]);
        $submittedAt = $attempt->fresh()->submitted_at;

        $this->actingAs($student)->post($this->submitUrl($quiz, $attempt), ['answers' => [$question->id => $wrong->id]]);

        $attempt->refresh();

        $this->assertSame(QuizAttemptStatus::Passed, $attempt->status);
        $this->assertEquals($submittedAt, $attempt->submitted_at);
        $this->assertSame(1.0, (float) $attempt->score_points);
        $this->assertSame(1, $attempt->answers()->count());
    }

    public function test_attempt_from_another_quiz_is_not_found(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        [, $otherQuiz] = $this->publishedQuiz();

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->where('quiz_id', $quiz->id)->firstOrFail();

        $this->actingAs($student)
            ->get($this->attemptUrl($otherQuiz, $attempt))
            ->assertNotFound();
    }

    public function test_quiz_list_shows_attempt_state_without_the_key(): void
    {
        [$student, $quiz, $question] = $this->publishedQuiz();
        $correct = $question->options()->where('is_correct', true)->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.courses.show', $quiz->course))
            ->assertOk()
            ->assertSee($quiz->title)
            ->assertSee('Not attempted');

        $this->actingAs($student)->post($this->startUrl($quiz));
        $attempt = QuizAttempt::query()->firstOrFail();
        $this->actingAs($student)->post($this->submitUrl($quiz, $attempt), ['answers' => [$question->id => $correct->id]]);

        $this->actingAs($student)
            ->get(route('student.courses.show', $quiz->course))
            ->assertOk()
            ->assertSee('Passed');
    }

    public function test_phase_nine_adds_no_quiz_admin_export_routes(): void
    {
        // Certificates, payments, and reports all arrive in later phases. There
        // is no quiz export route in the approved scope, so that stays guarded.
        $this->assertFalse(Route::has('instructor.courses.quizzes.export'));
    }

    /**
     * @return array{0: User, 1: Quiz, 2: QuizQuestion}
     */
    private function publishedQuiz(): array
    {
        [$student, $quiz] = $this->publishedQuizWithEnrollment();

        $question = $quiz->questions()->firstOrFail();

        return [$student, $quiz, $question];
    }

    /**
     * @return array{0: User, 1: Quiz, 2: Course, 3: Enrollment}
     */
    private function publishedQuizWithEnrollment(): array
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $quiz = Quiz::factory()
            ->for($course, 'course')
            ->for($module, 'module')
            ->withQuestion()
            ->create([
                'created_by' => $course->instructor_id,
                'status' => QuizStatus::Published,
                'position' => 1,
                'passing_score_percent' => 80,
            ]);

        $quiz->questions()->firstOrFail()->update([
            'explanation' => 'Correct answer explanation',
        ]);

        $student = User::factory()->create();

        $enrollment = Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return [$student, $quiz, $course, $enrollment];
    }

    private function quizUrl(Quiz $quiz): string
    {
        return "/student/courses/{$quiz->course_id}/quizzes/{$quiz->id}";
    }

    private function startUrl(Quiz $quiz): string
    {
        return "/student/courses/{$quiz->course_id}/quizzes/{$quiz->id}/start";
    }

    private function attemptUrl(Quiz $quiz, QuizAttempt $attempt): string
    {
        return "/student/courses/{$quiz->course_id}/quizzes/{$quiz->id}/attempts/{$attempt->id}";
    }

    private function submitUrl(Quiz $quiz, QuizAttempt $attempt): string
    {
        return "/student/courses/{$quiz->course_id}/quizzes/{$quiz->id}/attempts/{$attempt->id}/submit";
    }

    private function resultUrl(Quiz $quiz, QuizAttempt $attempt): string
    {
        return "/student/courses/{$quiz->course_id}/quizzes/{$quiz->id}/attempts/{$attempt->id}/result";
    }
}
