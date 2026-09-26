<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use Illuminate\Support\Collection;

/**
 * The only place a completion percentage is produced.
 */
class ProgressCalculator
{
    /**
     * @return array{completed: int, total: int, percentage: int}
     */
    public function forEnrollment(Enrollment $enrollment): array
    {
        $requiredLessonIds = $this->requiredPublishedLessonIds($enrollment->course);

        $completed = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('status', LessonProgressStatus::Completed)
            ->whereIn('lesson_id', $requiredLessonIds)
            ->count();

        $total = $requiredLessonIds->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $this->percentage($completed, $total),
        ];
    }

    /**
     * Lesson ids this Student has completed, keyed for lookup.
     *
     * @return Collection<int, true>
     */
    public function completedLessonIds(Enrollment $enrollment): Collection
    {
        return LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('status', LessonProgressStatus::Completed)
            ->pluck('lesson_id')
            ->flip();
    }

    public function isVisibleFor(Course $course): bool
    {
        return $course->status === CourseStatus::Published;
    }

    /**
     * Only required Lessons in published Modules count toward progress.
     *
     * @return Collection<int, int>
     */
    private function requiredPublishedLessonIds(Course $course): Collection
    {
        return $course->lessons()
            ->where('lessons.status', ContentStatus::Published)
            ->where('lessons.is_required', true)
            ->whereHas('module', fn ($query) => $query->where('modules.status', ContentStatus::Published))
            ->pluck('lessons.id');
    }

    private function percentage(int $completed, int $total): int
    {
        if ($total === 0) {
            return 0;
        }

        return (int) round($completed / $total * 100);
    }
}
