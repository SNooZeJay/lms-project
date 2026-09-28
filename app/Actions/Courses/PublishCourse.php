<?php

namespace App\Actions\Courses;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Events\ContentPublished;
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

            /*
             | One event, and it is here rather than in the Create actions.
             |
             | The plan says to dispatch from CreateLesson, CreateModule and
             | CreateLearningMaterial and never from the Update ones, so an edit
             | is not announced. Its intent is right and its location is not: all
             | three Create actions write a DRAFT, and a draft lesson fails the
             | published half of LessonPolicy::viewForStudent, so a notice about
             | one would carry a link that answers 403. Worse, in this application
             | there is no action that publishes an individual lesson at all, so
             | dispatching at creation would announce something permanently
             | invisible and never announce the moment content actually appears.
             |
             | This is that moment, and it is also the only one: publishing a
             | course publishes its modules and lessons with it. The status
             | travels on the event so a listener never re-reads the model and
             | finds it changed since, and the dedup key names the course so the
             | transition fires once however many times the button is pressed.
             */
            ContentPublished::dispatch($course, $course, ContentStatus::Published);

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
