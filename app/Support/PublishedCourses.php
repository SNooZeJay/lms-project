<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One published course query, shared by the public home page and the catalog.
 *
 * The two pages have to agree. If the home page could show a course the catalog
 * hides, the page would advertise something a student then cannot open, so the
 * definition of a publicly visible course lives here once.
 */
class PublishedCourses
{
    /**
     * Courses that anyone may see, newest first.
     *
     * Module and lesson counts are restricted to published content, because a
     * draft lesson is not part of what a student is being offered.
     */
    public static function query(): Builder
    {
        return Course::query()
            ->where('courses.status', CourseStatus::Published)
            ->with('instructor:id,name')
            ->withCount([
                'modules as published_modules_count' => fn ($query) => $query
                    ->where('modules.status', ContentStatus::Published),
                'lessons as published_lessons_count' => fn ($query) => $query
                    ->where('lessons.status', ContentStatus::Published),
            ])
            ->orderByDesc('courses.published_at')
            ->orderBy('courses.title');
    }

    /**
     * The free and paid groups the home page shows side by side.
     *
     * @return array{free: Collection<int, Course>, paid: Collection<int, Course>}
     */
    public static function forHomePage(int $perGroup = 3): array
    {
        $published = static::query()->get();

        return [
            'free' => $published
                ->where('course_type', CourseType::Free)
                ->take($perGroup)
                ->values(),
            'paid' => $published
                ->where('course_type', CourseType::Paid)
                ->take($perGroup)
                ->values(),
        ];
    }
}
