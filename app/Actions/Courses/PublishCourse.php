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

class PublishCourse
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Course $course): Course
    {
        Gate::forUser($actor)->authorize('publish', $course);

        if ($course->status !== CourseStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft course can be published.',
            ]);
        }

        $this->ensureCourseHasContent($course);

        return DB::transaction(function () use ($course): Course {
            $course->forceFill([
                'status' => CourseStatus::Published,
                'published_at' => now(),
            ]);
            $course->save();

            $moduleIds = $course->modules()->pluck('id');
            Module::query()->whereIn('id', $moduleIds)->update(['status' => ContentStatus::Published]);

            $lessonIds = Lesson::query()->whereIn('module_id', $moduleIds)->pluck('id');
            Lesson::query()->whereIn('id', $lessonIds)->update(['status' => ContentStatus::Published]);

            return $course;
        });
    }

    private function ensureCourseHasContent(Course $course): void
    {
        if ($course->modules()->doesntExist()) {
            throw ValidationException::withMessages([
                'status' => 'Add at least one module before publishing this course.',
            ]);
        }

        if ($course->lessons()->doesntExist()) {
            throw ValidationException::withMessages([
                'status' => 'Add at least one lesson before publishing this course.',
            ]);
        }
    }
}
