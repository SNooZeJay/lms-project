<?php

namespace Tests\Feature\Phase9;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorQuizAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_add_a_quiz_to_an_owned_course(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();

        $this->actingAs($instructor)
            ->post($this->storeUrl($course), [
                'title' => 'Networking basics',
                'description' => 'A short check.',
                'module_id' => $module->id,
                'passing_score_percent' => 70,
                'max_attempts' => 3,
            ])
            ->assertRedirect($this->showUrl($course));

        $quiz = Quiz::query()->firstOrFail();

        $this->assertSame('Networking basics', $quiz->title);
        $this->assertSame(QuizStatus::Draft, $quiz->status);
        $this->assertSame($instructor->id, $quiz->created_by);
        $this->assertSame(1, $quiz->position);
        $this->assertFalse($quiz->is_required);
    }

    public function test_quiz_positions_increase_within_the_course(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();

        foreach (['First', 'Second'] as $title) {
            $this->actingAs($instructor)->post($this->storeUrl($course), [
                'title' => $title,
                'module_id' => $module->id,
            ]);
        }

        $this->assertSame([1, 2], Quiz::query()->orderBy('position')->pluck('position')->all());
    }

    public function test_quiz_requires_a_title_and_a_valid_passing_score(): void
    {
        [$instructor, $course] = $this->courseWithModule();

        $this->actingAs($instructor)
            ->from($this->showUrl($course))
            ->post($this->storeUrl($course), ['title' => '', 'passing_score_percent' => 140])
            ->assertSessionHasErrors(['title', 'passing_score_percent']);

        $this->assertSame(0, Quiz::query()->count());
    }

    public function test_instructor_can_add_a_question_with_options(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        $this->actingAs($instructor)
            ->post($this->questionUrl($course, $quiz), [
                'prompt' => 'Which layer moves packets?',
                'points' => 2,
                'explanation' => 'The network layer routes packets.',
                'options' => [
                    ['option_text' => 'Data link', 'is_correct' => '0'],
                    ['option_text' => 'Network', 'is_correct' => '1'],
                ],
            ])
            ->assertRedirect($this->showUrl($course));

        $question = QuizQuestion::query()->firstOrFail();
        $options = $question->options()->get();

        $this->assertSame('Which layer moves packets?', $question->prompt);
        $this->assertSame(1, $question->position);
        $this->assertSame(2.0, (float) $question->points);
        $this->assertCount(2, $options);
        $this->assertSame(1, $options->where('is_correct', true)->count());
    }

    public function test_a_question_must_have_exactly_one_correct_option(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        // None correct.
        $this->actingAs($instructor)
            ->from($this->showUrl($course))
            ->post($this->questionUrl($course, $quiz), [
                'prompt' => 'No key',
                'options' => [
                    ['option_text' => 'A', 'is_correct' => '0'],
                    ['option_text' => 'B', 'is_correct' => '0'],
                ],
            ])
            ->assertSessionHasErrors('options');

        // Two correct.
        $this->actingAs($instructor)
            ->from($this->showUrl($course))
            ->post($this->questionUrl($course, $quiz), [
                'prompt' => 'Two keys',
                'options' => [
                    ['option_text' => 'A', 'is_correct' => '1'],
                    ['option_text' => 'B', 'is_correct' => '1'],
                ],
            ])
            ->assertSessionHasErrors('options');

        $this->assertSame(0, QuizQuestion::query()->count());
    }

    public function test_a_question_needs_at_least_two_options(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        $this->actingAs($instructor)
            ->from($this->showUrl($course))
            ->post($this->questionUrl($course, $quiz), [
                'prompt' => 'One option only',
                'options' => [
                    ['option_text' => 'Only', 'is_correct' => '1'],
                ],
            ])
            ->assertSessionHasErrors('options');

        $this->assertSame(0, QuizQuestion::query()->count());
    }

    public function test_question_positions_increase_within_the_quiz(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        foreach (['First', 'Second'] as $prompt) {
            $this->actingAs($instructor)->post($this->questionUrl($course, $quiz), [
                'prompt' => $prompt,
                'options' => [
                    ['option_text' => 'A', 'is_correct' => '1'],
                    ['option_text' => 'B', 'is_correct' => '0'],
                ],
            ]);
        }

        $this->assertSame([1, 2], QuizQuestion::query()->orderBy('position')->pluck('position')->all());
    }

    public function test_instructor_can_update_a_quiz(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        $this->actingAs($instructor)
            ->patch($this->quizUrl($course, $quiz), [
                'title' => 'Updated title',
                'passing_score_percent' => 60,
                'max_attempts' => 2,
            ])
            ->assertRedirect($this->showUrl($course));

        $quiz->refresh();

        $this->assertSame('Updated title', $quiz->title);
        $this->assertSame('60.00', $quiz->passing_score_percent);
        $this->assertSame(2, $quiz->max_attempts);
    }

    public function test_instructor_can_publish_and_archive_a_quiz(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor);

        $this->actingAs($instructor)
            ->post($this->publishUrl($course, $quiz))
            ->assertRedirect($this->showUrl($course));

        $this->assertSame(QuizStatus::Published, $quiz->fresh()->status);

        $this->actingAs($instructor)
            ->post($this->archiveUrl($course, $quiz))
            ->assertRedirect($this->showUrl($course));

        $this->assertSame(QuizStatus::Archived, $quiz->fresh()->status);
    }

    public function test_a_quiz_cannot_be_published_without_questions(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        $this->actingAs($instructor)
            ->from($this->showUrl($course))
            ->post($this->publishUrl($course, $quiz))
            ->assertSessionHasErrors();

        $this->assertSame(QuizStatus::Draft, $quiz->fresh()->status);
    }

    public function test_publishing_requires_exactly_one_correct_option_per_question(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        $question = new QuizQuestion;
        $question->forceFill(['quiz_id' => $quiz->id, 'prompt' => 'Broken', 'position' => 1, 'points' => 1]);
        $question->save();

        foreach ([1 => 'A', 2 => 'B'] as $slot => $text) {
            $option = new QuizOption;
            $option->forceFill([
                'question_id' => $question->id,
                'option_text' => $text,
                'position' => $slot,
                'is_correct' => false,
            ]);
            $option->save();
        }

        $this->actingAs($instructor)
            ->from($this->showUrl($course))
            ->post($this->publishUrl($course, $quiz))
            ->assertSessionHasErrors();

        $this->assertSame(QuizStatus::Draft, $quiz->fresh()->status);
    }

    public function test_another_instructor_cannot_manage_a_quiz(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);
        $stranger = User::factory()->instructor()->create();

        $this->actingAs($stranger)
            ->patch($this->quizUrl($course, $quiz), ['title' => 'Hijacked'])
            ->assertForbidden();
        $this->actingAs($stranger)
            ->post($this->publishUrl($course, $quiz))
            ->assertForbidden();
        $this->actingAs($stranger)
            ->post($this->questionUrl($course, $quiz), [
                'prompt' => 'Nope',
                'options' => [
                    ['option_text' => 'A', 'is_correct' => '1'],
                    ['option_text' => 'B', 'is_correct' => '0'],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, QuizQuestion::query()->count());
    }

    public function test_student_and_administrator_cannot_manage_a_quiz(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        $student = User::factory()->create();
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($student)->patch($this->quizUrl($course, $quiz), ['title' => 'No'])->assertForbidden();
        $this->actingAs($administrator)->patch($this->quizUrl($course, $quiz), ['title' => 'No'])->assertForbidden();
        $this->actingAs($student)->post($this->publishUrl($course, $quiz))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        $this->post($this->storeUrl($course), ['title' => 'No'])->assertRedirect(route('login'));
        $this->patch($this->quizUrl($course, $quiz), ['title' => 'No'])->assertRedirect(route('login'));
    }

    public function test_a_module_from_another_course_is_rejected(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        [$otherInstructor, $otherCourse, $otherModule] = $this->courseWithModule();

        $this->actingAs($instructor)
            ->from($this->showUrl($course))
            ->post($this->storeUrl($course), ['title' => 'Bad module', 'module_id' => $otherModule->id])
            ->assertSessionHasErrors('module_id');
    }

    public function test_outline_lists_quizzes_with_their_question_count(): void
    {
        [$instructor, $course, $module] = $this->courseWithModule();
        $quiz = $this->draftQuiz($course, $module, $instructor, false);

        $this->actingAs($instructor)
            ->get($this->showUrl($course))
            ->assertOk()
            ->assertSee('Quizzes')
            ->assertSee($quiz->title)
            ->assertSee('0 questions')
            ->assertSee('Add quiz');
    }

    /**
     * @return array{0: User, 1: Course, 2: Module}
     */
    private function courseWithModule(): array
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Draft,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        return [$instructor, $course, $module];
    }

    private function draftQuiz(Course $course, Module $module, User $instructor, bool $withQuestion = true): Quiz
    {
        $factory = Quiz::factory()->for($course, 'course')->for($module, 'module');

        if ($withQuestion) {
            $factory = $factory->withQuestion();
        }

        return $factory->create([
            'created_by' => $instructor->id,
            'status' => QuizStatus::Draft,
            'position' => 1,
        ]);
    }

    private function showUrl(Course $course): string
    {
        return "/instructor/courses/{$course->id}";
    }

    private function storeUrl(Course $course): string
    {
        return "/instructor/courses/{$course->id}/quizzes";
    }

    private function quizUrl(Course $course, Quiz $quiz): string
    {
        return "/instructor/courses/{$course->id}/quizzes/{$quiz->id}";
    }

    private function publishUrl(Course $course, Quiz $quiz): string
    {
        return "/instructor/courses/{$course->id}/quizzes/{$quiz->id}/publish";
    }

    private function archiveUrl(Course $course, Quiz $quiz): string
    {
        return "/instructor/courses/{$course->id}/quizzes/{$quiz->id}/archive";
    }

    private function questionUrl(Course $course, Quiz $quiz): string
    {
        return "/instructor/courses/{$course->id}/quizzes/{$quiz->id}/questions";
    }
}
