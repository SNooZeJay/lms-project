<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\NotificationType;
use App\Events\LessonCompleted;
use App\Events\LessonStarted;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Tells an instructor that a student opened or finished a lesson.
 *
 * One listener for both events rather than two, because they differ in one word
 * and in one notification type. Two listeners would be two copies of the
 * recipient rules, and the copy is where the mistakes live.
 *
 * The recipient is the instructor who owns the course, resolved from the
 * enrollment rather than passed in. The event cannot be told the wrong
 * instructor, because it does not carry one.
 */
class NotifyInstructorOfLessonActivity
{
    public function __construct(private readonly RecordNotification $notify) {}

    public function handleStarted(LessonStarted $event): void
    {
        $this->record($event->enrollment, $event->lesson, NotificationType::LessonStarted, 'started');
    }

    public function handleCompleted(LessonCompleted $event): void
    {
        $this->record($event->enrollment, $event->lesson, NotificationType::LessonCompleted, 'completed');
    }

    private function record(Enrollment $enrollment, Lesson $lesson, NotificationType $type, string $verb): void
    {
        $course = $enrollment->course;
        $instructor = $course->instructor;

        if ($instructor === null) {
            return;
        }

        $student = $enrollment->student;

        $this->notify->handle(
            $instructor,
            $type,
            "{$student->name} {$verb} a lesson",
            "{$student->name} {$verb} \"{$lesson->title}\" in {$course->title}.",
            course: $course,
            /*
             | Keyed on the transition, and the type is part of the key.
             |
             | The plan gives `enrollment:{id}:lesson:{id}` for both LESSON_STARTED
             | and LESSON_COMPLETED, which cannot be right: the key is unique per
             | recipient, so a student who opens a lesson and then finishes it
             | would produce the second row against the first and the instructor
             | would hear about only one of the two. The test that a student
             | starting then completing a lesson produces both notices is what
             | found it.
             *
             | Keyed on the transition rather than the pair, because the pair is
             | what a repeat visit repeats.
             */
            dedupKey: "enrollment:{$enrollment->id}:lesson:{$lesson->id}:{$type->value}",
            link: route('instructor.courses.show', $course),
            /*
             | The link has to be one the reader may actually open, or the notice
             | tells an instructor about a course page that refuses them. An
             | instructor who lost the course does not get told anything about it.
             */
            authorizeLink: fn (User $who): bool => Gate::forUser($who)->allows('view', $course),
            subjectType: 'enrollment',
            subjectId: $enrollment->id,
        );
    }
}
