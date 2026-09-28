<?php

namespace App\Actions\Courses\Curriculum;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Rebuilds Module and Lesson positions from a validated order.
 *
 * Positions are always renumbered from the lowest free slot and are never
 * read from the request. Rows are parked in a temporary range first so the
 * unique (owner, position) constraint is never violated mid-update.
 */
class ReorderCurriculum
{
    /**
     * @param  array<int, int>  $order
     */
    public function reorderModules(User $actor, Course $course, array $order): void
    {
        Gate::forUser($actor)->authorize('reorder', [Module::class, $course]);

        DB::transaction(function () use ($course, $order): void {
            $this->apply(
                $course,
                $course->modules()->where('status', '!=', ContentStatus::Archived),
                $order
            );
        });
    }

    /**
     * @param  array<int, int>  $order
     */
    public function reorderLessons(User $actor, Module $module, array $order): void
    {
        Gate::forUser($actor)->authorize('reorder', [Lesson::class, $module]);

        DB::transaction(function () use ($module, $order): void {
            $this->apply(
                $module,
                $module->lessons()->where('status', '!=', ContentStatus::Archived),
                $order
            );
        });
    }

    /**
     * @param  array<int, int>  $order
     */
    private function apply(Model $owner, HasMany $current, array $order): void
    {
        // The owner's row is locked for the whole renumber, so a second reorder
        // of the same list queues behind this one instead of interleaving with
        // it. Without the lock the unique constraint still stops two rows from
        // claiming one position, but the finished order can end up a mixture of
        // the two requests, which is a silent wrong answer rather than an error
        // the instructor can see and retry.
        $owner->newQuery()->whereKey($owner->getKey())->lockForUpdate()->first();

        $models = $current->get()->keyBy('id');

        $base = ((int) $models->min('position')) - 1;
        $target = [];

        foreach (array_values($order) as $index => $id) {
            if ($models->has($id)) {
                $target[$id] = $base + $index + 1;
            }
        }

        // Park every row above the real range so no swap collides with the
        // unique constraint, then write the final positions.
        $models->each(function (Model $model) use ($base): void {
            $model->forceFill(['position' => $base + $model->position + 1000])->save();
        });

        foreach ($target as $id => $position) {
            $models->get($id)->forceFill(['position' => $position])->save();
        }
    }
}
