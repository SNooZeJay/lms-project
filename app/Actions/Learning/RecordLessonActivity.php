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

class RecordLessonActivity
{
    /**
     * Record that a Student opened a Lesson. This never completes a Lesson
     * and never downgrades a completed record.
     */
    public function handle(User $actor, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        Gate::forUser($actor)->authorize('viewForStudent', $lesson);

        try {
            return DB::transaction(function () use ($actor, $enrollment, $lesson): LessonProgress {
                $progress = $this->findOrNew($actor, $enrollment, $lesson);

                $progress->last_viewed_at = now();

                if ($progress->status === LessonProgressStatus::NotStarted) {
                    $progress->status = LessonProgressStatus::InProgress;
                    $progress->started_at = now();
                }

                $progress->save();

                return $progress;
            });
        } catch (QueryException $exception) {
            $existing = $this->existing($enrollment, $lesson);

            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }
    }

    private function findOrNew(User $actor, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        $existing = $this->existing($enrollment, $lesson);

        if ($existing !== null) {
            return $existing;
        }

        $progress = new LessonProgress;
        $progress->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $actor->id,
            'lesson_id' => $lesson->id,
            'status' => LessonProgressStatus::NotStarted,
        ]);

        return $progress;
    }

    private function existing(Enrollment $enrollment, Lesson $lesson): ?LessonProgress
    {
        return LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)
            ->first();
    }
}
