<?php

namespace App\Enums;

/**
 * Which shape of conversation a thread is.
 *
 * One set of tables serves both, and this is the column that says which rules
 * apply. They are different questions with the same machinery behind them: a
 * course thread is scoped by an enrollment, a support thread by whoever raised
 * it, and a support thread has no course at all.
 */
enum ConversationKind: string
{
    case Course = 'course';
    case Support = 'support';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether a thread of this kind belongs to a course.
     *
     * A support thread is raised by a person about the system rather than about
     * a course, so attaching one to a course would be a category error the
     * unique index on (kind, course_id, requester_id) could not catch, because
     * it would look like a second course thread.
     */
    public function requiresCourse(): bool
    {
        return $this === self::Course;
    }
}
