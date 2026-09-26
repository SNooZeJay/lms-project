<?php

namespace App\Actions\Quizzes;

use App\Enums\QuizAttemptStatus;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Opens an Attempt for a Student.
 *
 * A Student may hold only one open Attempt at a time, at most three Attempts
 * in total, and never a new Attempt after passing. The unique
 * (quiz, student, attempt_number) rule plus a transaction makes two
 * concurrent first Attempts impossible.
 */
class StartQuizAttempt
{
    public function handle(User $actor, Quiz $quiz): QuizAttempt
    {
        Gate::forUser($actor)->authorize('startForStudent', $quiz);

        $enrollment = $this->enrollmentFor($actor, $quiz);

        return DB::transaction(function () use ($actor, $quiz, $enrollment): QuizAttempt {
            $open = $quiz->attempts()
                ->where('student_id', $actor->id)
                ->where('status', QuizAttemptStatus::InProgress)
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                return $open;
            }

            $used = (int) $quiz->attempts()->where('student_id', $actor->id)->count();
            $max = (int) $quiz->max_attempts;

            if ($quiz->attempts()->where('student_id', $actor->id)->where('passed', true)->exists()) {
                throw ValidationException::withMessages([
                    'quiz' => 'You already passed this quiz.',
                ]);
            }

            if ($used >= $max) {
                throw ValidationException::withMessages([
                    'quiz' => "You have used all {$max} attempts for this quiz.",
                ]);
            }

            $attempt = new QuizAttempt;
            $attempt->forceFill([
                'quiz_id' => $quiz->id,
                'student_id' => $actor->id,
                'enrollment_id' => $enrollment->id,
                'attempt_number' => $used + 1,
                'status' => QuizAttemptStatus::InProgress,
                'started_at' => now(),
            ]);

            try {
                $attempt->save();
            } catch (QueryException $exception) {
                // A concurrent request already created this attempt number.
                $existing = $quiz->attempts()
                    ->where('student_id', $actor->id)
                    ->where('attempt_number', $used + 1)
                    ->first();

                if ($existing === null) {
                    throw $exception;
                }

                return $existing;
            }

            return $attempt;
        });
    }

    private function enrollmentFor(User $actor, Quiz $quiz): Enrollment
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $actor->id)
            ->where('course_id', $quiz->course_id)
            ->first();

        if ($enrollment === null || ! $enrollment->grantsAccess()) {
            throw ValidationException::withMessages([
                'quiz' => 'You need an active enrollment to take this quiz.',
            ]);
        }

        return $enrollment;
    }
}
