<?php

namespace App\Actions\Learning;

use App\Enums\LessonProgressStatus;
use App\Events\LessonStarted;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecordLessonActivity
{
    /**
     * Record that a Student opened a Lesson. This never completes a Lesson and
     * never downgrades a completed record.
     *
     * This runs on every lesson page view, which makes it the most frequent
     * write in the application, and it used to read the row, change it in
     * memory and save the whole model back. Two consequences followed from
     * that, both of them silent:
     *
     *   - Two tabs open at once both found no row, both built one, and one lost
     *     on the unique (enrollment_id, lesson_id) constraint.
     *   - A student who completed the lesson in one tab and then reloaded it in
     *     another had the completion written back to not started, because the
     *     stale copy in memory was saved over the newer value.
     *
     * So the row is created only if it is missing, and every later statement
     * states the condition it is allowed to change. No statement can turn a
     * completed lesson back into an unfinished one, whatever order two requests
     * arrive in.
     */
    public function handle(User $actor, Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        Gate::forUser($actor)->authorize('viewForStudent', $lesson);

        return DB::transaction(function () use ($actor, $enrollment, $lesson): LessonProgress {
            $now = now();

            // Created only when absent. A duplicate is ignored rather than
            // raised, so two tabs opening the same lesson at once produce one
            // row and no error.
            LessonProgress::query()->insertOrIgnore([
                [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $actor->id,
                    'lesson_id' => $lesson->id,
                    'status' => LessonProgressStatus::NotStarted,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            // Advanced only from not started. Anything already in progress or
            // completed is left alone, so this cannot undo a completion that
            // landed a moment earlier.
            //
            // The affected row count is captured, and it is the entire reason a
            // repeat visit can be told apart from a first one. Zero means the
            // lesson was already open, so no event is dispatched and no
            // notification is written. That is a fact about the database rather
            // than a counter or a time window, which is the difference between a
            // rule and a heuristic.
            $started = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->where('status', LessonProgressStatus::NotStarted)
                ->update([
                    'status' => LessonProgressStatus::InProgress,
                    'started_at' => $now,
                    'updated_at' => $now,
                ]);

            // The visit itself. One column, no status, so it is safe to write
            // unconditionally and safe to write after the line above.
            LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->update([
                    'last_viewed_at' => $now,
                    'updated_at' => $now,
                ]);

            $progress = LessonProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->firstOrFail();

            /*
             | The event itself carries the after-commit rule, so there is nothing
             | to remember here and no way to dispatch one of these early.
             |
             | Only on a real transition. A repeat visit reports zero affected
             | rows and dispatches nothing, which is why opening the same lesson
             | five times cannot produce five notifications.
             */
            if ($started === 1) {
                LessonStarted::dispatch($enrollment, $lesson);
            }

            return $progress;
        });
    }
}
