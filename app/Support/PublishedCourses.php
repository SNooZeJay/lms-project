<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
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
     * The count is the number of cards drawn, capped at $perGroup, so a caller
     * asking for three gets three or fewer. The number of free or paid courses
     * in the catalog as a whole comes from totals() instead, which is what the
     * home page panel prints. Mixing the two would report a cap as a total.
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

    /**
     * How much the published catalog holds, counted across every course.
     *
     * WHY THIS IS NOT COUNTED FROM THE CARDS
     *
     * The home page draws three courses per group. Summing the counts printed on
     * those cards would describe six courses and label the result as the catalog.
     * With five courses in this repository the two happen to agree, and they would
     * stop agreeing the moment a sixth was published, at which point the panel
     * would quietly become a lie that nothing would catch. So this counts the
     * whole published catalog rather than the slice drawn above it.
     *
     * The free and paid counts had the same defect before this existed. The panel
     * read $freeCourses->count(), which is the number of cards drawn and therefore
     * capped at three. Eight free courses printed as three. It read correctly only
     * because this repository happens to hold two, so the cap was never reached.
     *
     * WHY IT IS SCOPED TO PUBLISHED CONTENT
     *
     * A draft lesson is not part of what a visitor is being offered, and the per
     * card counts already say so. A panel counting drafts would promise more than
     * a student can open.
     *
     * Each count is scoped in the query rather than by filtering one loaded
     * collection in memory, because lessons are reached through their module and
     * a single in-memory pass cannot see whether the parent module is itself a
     * draft. The lesson count therefore nests the module subquery, and a draft
     * module's lessons are excluded whether the module sits in a draft course or
     * is only itself unpublished.
     *
     * The course counts come from one grouped query rather than three, because
     * the split is a property of the same rows and asking three times reads the
     * same table three times to learn something one pass already knew.
     *
     * @return array{courses: int, free: int, paid: int, modules: int, lessons: int, quizzes: int}
     */
    public static function totals(): array
    {
        $byType = Course::query()
            ->where('courses.status', CourseStatus::Published)
            ->selectRaw('courses.course_type, COUNT(*) AS total')
            ->groupBy('courses.course_type')
            ->pluck('total', 'courses.course_type');

        // The lessons are counted through their module, and the module through its
        // course, so a lesson cannot be counted unless both of its parents are
        // published content inside a published course.
        $publishedLessons = Lesson::query()
            ->select('lessons.id')
            ->where('lessons.status', ContentStatus::Published)
            ->whereIn('lessons.module_id', Module::query()
                ->select('modules.id')
                ->where('modules.status', ContentStatus::Published)
                ->whereIn('modules.course_id', Course::query()
                    ->select('courses.id')
                    ->where('courses.status', CourseStatus::Published)));

        return [
            'courses' => (int) $byType->sum(),
            'free' => (int) $byType->get(CourseType::Free->value, 0),
            'paid' => (int) $byType->get(CourseType::Paid->value, 0),
            'modules' => Module::query()
                ->where('modules.status', ContentStatus::Published)
                ->whereIn('modules.course_id', Course::query()
                    ->select('courses.id')
                    ->where('courses.status', CourseStatus::Published))
                ->count(),
            'lessons' => (clone $publishedLessons)->count(),
            'quizzes' => Quiz::query()
                ->where('quizzes.status', ContentStatus::Published)
                ->whereIn('quizzes.course_id', Course::query()
                    ->select('courses.id')
                    ->where('courses.status', CourseStatus::Published))
                ->count(),
        ];
    }
}
