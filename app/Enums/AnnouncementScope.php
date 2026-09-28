<?php

namespace App\Enums;

/**
 * Who an announcement is for.
 *
 * Two values and no third, because the interesting case is not a third kind of
 * announcement but the absence of a course. A platform announcement belongs to
 * nobody's course; a course announcement belongs to exactly one.
 *
 * Deliberately free of query logic. The visible-to-one-person rule belongs on
 * Announcement as a scope, where Eloquent's conventions put it, rather than
 * here, where it would be a scope on an enum.
 */
enum AnnouncementScope: string
{
    case Course = 'course';
    case Platform = 'platform';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresCourse(): bool
    {
        return $this === self::Course;
    }
}
