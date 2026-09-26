<?php

namespace App\Actions\Learning;

use App\Enums\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MarkLessonComplete
{
    /**
     * Mark a Lesson complete. Calling this twice keeps one record and the
     * original completion time. No field is ever read from a request.
     */
    public function handle(User $actor, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        Gate::forUser($actor)->authorize('completeForStudent', $lesson);

        return DB::transaction(function () use ($actor, $enrollment, $lesson): LessonProgress {
            $progress = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->first();

            if ($progress === null) {
                $progress = new LessonProgress;
                $progress->forceFill([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $actor->id,
                    'lesson_id' => $lesson->id,
                    'status' => LessonProgressStatus::InProgress,
                    'started_at' => now(),
                ]);
            }

            if ($progress->status === LessonProgressStatus::Completed) {
                $progress->last_viewed_at = now();
                $progress->save();

                return $progress;
            }

            $progress->forceFill([
                'student_id' => $actor->id,
                'status' => LessonProgressStatus::Completed,
                'completed_at' => now(),
                'last_viewed_at' => now(),
            ]);
            $progress->save();

            return $progress;
        });
    }

    /**
     * Kept for symmetry with RecordLessonActivity when a race creates the row twice.
     */
    public function handleSafely(User $actor, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        try {
            return $this->handle($actor, $enrollment, $lesson);
        } catch (QueryException $exception) {
            $existing = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }
    }
}
