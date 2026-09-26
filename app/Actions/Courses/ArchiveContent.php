<?php

namespace App\Actions\Courses;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Archive is a status change, never a delete.
 *
 * Enrollments, lesson progress, payments, and certificates are never touched.
 * Restoring returns the row to `draft` so the Instructor publishes it again
 * deliberately.
 */
class ArchiveContent
{
    public function archiveCourse(User $actor, Course $course): Course
    {
        Gate::forUser($actor)->authorize('archive', $course);

        return $this->archive($course, CourseStatus::Archived);
    }

    public function restoreCourse(User $actor, Course $course): Course
    {
        Gate::forUser($actor)->authorize('restore', $course);

        return $this->restore($course, CourseStatus::Draft, CourseStatus::Archived);
    }

    public function archiveModule(User $actor, Module $module): Module
    {
        Gate::forUser($actor)->authorize('update', $module);

        return $this->archive($module, ContentStatus::Archived);
    }

    public function restoreModule(User $actor, Module $module): Module
    {
        Gate::forUser($actor)->authorize('update', $module);

        return $this->restore($module, ContentStatus::Draft, ContentStatus::Archived);
    }

    public function archiveLesson(User $actor, Lesson $lesson): Lesson
    {
        Gate::forUser($actor)->authorize('update', $lesson);

        return $this->archive($lesson, ContentStatus::Archived);
    }

    public function restoreLesson(User $actor, Lesson $lesson): Lesson
    {
        Gate::forUser($actor)->authorize('update', $lesson);

        return $this->restore($lesson, ContentStatus::Draft, ContentStatus::Archived);
    }

    /**
     * @template T of Model
     *
     * @param  T  $model
     * @return T
     */
    private function archive(Model $model, CourseStatus|ContentStatus $archived): Model
    {
        return DB::transaction(function () use ($model, $archived): Model {
            if ($model->getAttribute('status') === $archived) {
                throw ValidationException::withMessages([
                    'status' => 'This content is already archived.',
                ]);
            }

            $model->forceFill(['status' => $archived]);
            $model->save();

            return $model->refresh();
        });
    }

    /**
     * @template T of Model
     *
     * @param  T  $model
     * @return T
     */
    private function restore(Model $model, CourseStatus|ContentStatus $draft, CourseStatus|ContentStatus $archived): Model
    {
        return DB::transaction(function () use ($model, $draft, $archived): Model {
            if ($model->getAttribute('status') !== $archived) {
                throw ValidationException::withMessages([
                    'status' => 'Only archived content can be restored.',
                ]);
            }

            $model->forceFill(['status' => $draft]);
            $model->save();

            return $model->refresh();
        });
    }
}
