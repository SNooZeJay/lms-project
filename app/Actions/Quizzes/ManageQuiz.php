<?php

namespace App\Actions\Quizzes;

use App\Enums\QuizStatus;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\User;
use App\Support\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Authoring rules for a Quiz.
 *
 * Positions, status, and the answer key are all server-owned. A Quiz can only
 * be published when every Question has exactly one correct Option.
 */
class ManageQuiz
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Course $course, array $data): Quiz
    {
        Gate::forUser($actor)->authorize('create', [Quiz::class, $course]);

        return DB::transaction(function () use ($actor, $course, $data): Quiz {
            $quiz = new Quiz;
            $quiz->forceFill([
                'course_id' => $course->id,
                'module_id' => $data['module_id'] ?? null,
                'lesson_id' => $data['lesson_id'] ?? null,
                'created_by' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'position' => Position::reserve($course, $course->quizzes()),
                'status' => QuizStatus::Draft,
                'is_required' => (bool) ($data['is_required'] ?? false),
                'passing_score_percent' => $data['passing_score_percent'] ?? 80,
                'max_attempts' => $data['max_attempts'] ?? 3,
            ]);
            $quiz->save();

            return $quiz;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Quiz $quiz, array $data): Quiz
    {
        Gate::forUser($actor)->authorize('update', $quiz);

        return DB::transaction(function () use ($quiz, $data): Quiz {
            $quiz->forceFill([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'module_id' => $data['module_id'] ?? null,
                'is_required' => (bool) ($data['is_required'] ?? false),
                'passing_score_percent' => $data['passing_score_percent'] ?? 80,
                'max_attempts' => $data['max_attempts'] ?? 3,
            ]);
            $quiz->save();

            return $quiz;
        });
    }

    public function publish(User $actor, Quiz $quiz): Quiz
    {
        Gate::forUser($actor)->authorize('update', $quiz);

        return DB::transaction(function () use ($quiz): Quiz {
            $this->ensurePublishable($quiz);

            $quiz->forceFill(['status' => QuizStatus::Published]);
            $quiz->save();

            return $quiz;
        });
    }

    public function archive(User $actor, Quiz $quiz): Quiz
    {
        Gate::forUser($actor)->authorize('archive', $quiz);

        return DB::transaction(function () use ($quiz): Quiz {
            $quiz->forceFill(['status' => QuizStatus::Archived]);
            $quiz->save();

            return $quiz;
        });
    }

    private function ensurePublishable(Quiz $quiz): void
    {
        $questions = $quiz->questions()->with('options')->get();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'quiz' => 'Add at least one question before publishing this quiz.',
            ]);
        }

        foreach ($questions as $question) {
            if ($question->options->where('is_correct', true)->count() !== 1) {
                throw ValidationException::withMessages([
                    'quiz' => 'Every question needs exactly one correct answer before publishing.',
                ]);
            }
        }
    }
}
