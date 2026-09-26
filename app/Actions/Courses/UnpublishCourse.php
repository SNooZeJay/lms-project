<?php

namespace App\Actions\Courses;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UnpublishCourse
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Course $course): Course
    {
        Gate::forUser($actor)->authorize('unpublish', $course);

        if ($course->status !== CourseStatus::Published) {
            throw ValidationException::withMessages([
                'status' => 'Only a published course can be unpublished.',
            ]);
        }

        return DB::transaction(function () use ($course): Course {
            $course->forceFill(['status' => CourseStatus::Draft]);
            $course->save();

            $moduleIds = $course->modules()->pluck('id');
            Module::query()->whereIn('id', $moduleIds)->update(['status' => ContentStatus::Draft]);

            $lessonIds = Lesson::query()->whereIn('module_id', $moduleIds)->pluck('id');
            Lesson::query()->whereIn('id', $lessonIds)->update(['status' => ContentStatus::Draft]);

            return $course;
        });
    }
}
