<?php

namespace App\Actions\Learning;

use App\Enums\LessonProgressStatus;
use App\Events\LessonCompleted;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MarkLessonComplete
{
    /**
     * Mark a Lesson complete. Calling this twice keeps one record and the
     * original completion time. No field is ever read from a request.
     *
     * The row is written with an atomic upsert rather than read first and then
     * inserted. A student double clicking "mark complete", or opening the lesson
     * in two tabs, sends two requests that both find no existing row and both
     * try to create one. One of them then loses on the unique
     * (enrollment_id, lesson_id) constraint and the student is shown a server
     * error for an action that actually succeeded in the other tab. Letting the
     * database decide which request wins removes the error without removing the
     * single row.
     */
    public function handle(User $actor, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        Gate::forUser($actor)->authorize('completeForStudent', $lesson);

        return DB::transaction(function () use ($actor, $enrollment, $lesson): LessonProgress {
            $now = now();

            /*
             | Read before writing, so the event can mean "became complete"
             | rather than "is complete".
             |
             | upsert reports affected rows, not whether the status changed, and
             | it deliberately rewrites status every time so a second press still
             | fixes a row left half written. That is the right behaviour for the
             | write and the wrong signal for a notice, so the previous value is
             | read here instead.
             |
             | Two requests arriving together can both read not-complete and both
             | dispatch. The dedup key on (user, enrollment, lesson) is the
             | backstop for that, exactly as it is for the quiz rows: the unique
             | index drops the second row. The check narrows the common case and
             | the index settles the race.
             */
            $wasCompleted = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->where('status', LessonProgressStatus::Completed)
                ->exists();

            // One statement, so there is no window between "is there a row" and
            // "write the row" for a second request to slip into.
            //
            // The row is written already completed rather than created as
            // started and then updated, which is the same end state in one
            // statement rather than two.
            LessonProgress::query()->upsert(
                [[
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $actor->id,
                    'lesson_id' => $lesson->id,
                    'status' => LessonProgressStatus::Completed,
                    'started_at' => $now,
                    'completed_at' => $now,
                    'last_viewed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['enrollment_id', 'lesson_id'],
                // Only the columns an upsert is allowed to overwrite. created_at
                // is absent, and so is completed_at: a second press must not
                // move the moment the student actually finished.
                ['status', 'last_viewed_at', 'student_id', 'updated_at']
            );

            $progress = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->firstOrFail();

            if (! $wasCompleted) {
                LessonCompleted::dispatch($enrollment, $lesson);
            }

            return $progress;
        });
    }
}
