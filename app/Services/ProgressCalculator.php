<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Collection;

/**
 * The only place a completion percentage is produced.
 *
 * There is a batched read here as well as a single one, and the single one is
 * written in terms of the batched one so the two can never disagree. A student
 * dashboard used to call the single read once per enrollment, which made the
 * query count grow with the number of courses a student had taken: four
 * enrollments cost thirteen queries and twelve cost thirty seven. Both numbers
 * are now three, whatever the student is doing.
 */
class ProgressCalculator
{
    /**
     * @return array{completed: int, total: int, percentage: int}
     */
    public function forEnrollment(Enrollment $enrollment): array
    {
        return $this->forEnrollments(collect([$enrollment]))->get($enrollment->id, [
            'completed' => 0,
            'total' => 0,
            'percentage' => 0,
        ]);
    }

    /**
     * Progress for many enrollments in a fixed number of queries.
     *
     * Two queries regardless of how many enrollments are passed in: one for the
     * required Lesson ids of every course involved, and one for how many of them
     * each enrollment has completed.
     *
     * The completed counts are taken against the union of the required Lesson
     * ids rather than per enrollment, which relies on an invariant the schema
     * and the application both hold: a Lesson progress row only ever points at
     * a Lesson inside its own enrollment's course. That is enforced where the
     * row is written, so the union cannot admit a row the per enrollment read
     * would have rejected.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @return Collection<int, array{completed: int, total: int, percentage: int}>
     */
    public function forEnrollments(Collection $enrollments): Collection
    {
        $keyed = $enrollments->keyBy('id');

        if ($keyed->isEmpty()) {
            return collect();
        }

        $requiredByCourse = $this->requiredPublishedLessonIdsByCourse(
            $keyed->pluck('course_id')->unique()->values()
        );

        /** @var array<int, int> $totalByCourse */
        $totalByCourse = [];

        /** @var array<int, true> $allRequiredLessonIds */
        $allRequiredLessonIds = [];

        foreach ($requiredByCourse as $courseId => $lessonIds) {
            $totalByCourse[$courseId] = $lessonIds->count();

            foreach ($lessonIds as $lessonId) {
                $allRequiredLessonIds[$lessonId] = true;
            }
        }

        $completedByEnrollment = $this->completedCountsByEnrollment(
            $keyed->keys()->all(),
            array_keys($allRequiredLessonIds)
        );

        return $keyed->map(function (Enrollment $enrollment) use ($totalByCourse, $completedByEnrollment): array {
            $total = (int) ($totalByCourse[$enrollment->course_id] ?? 0);
            $completed = (int) ($completedByEnrollment[$enrollment->id] ?? 0);

            return [
                'completed' => $completed,
                'total' => $total,
                'percentage' => $this->percentage($completed, $total),
            ];
        });
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
     * Required Lesson ids in published Modules, keyed by course.
     *
     * @param  Collection<int, int>  $courseIds
     * @return array<int, Collection<int, int>>
     */
    private function requiredPublishedLessonIdsByCourse(Collection $courseIds): array
    {
        if ($courseIds->isEmpty()) {
            return [];
        }

        // A Lesson does not carry course_id itself: it belongs to a Module, and
        // the Module belongs to the Course. The join is how a lesson is traced
        // back to its course, so the course id is read from the module and
        // aliased rather than read from a column that does not exist.
        $rows = Lesson::query()
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->whereIn('modules.course_id', $courseIds)
            ->where('lessons.status', ContentStatus::Published)
            ->where('lessons.is_required', true)
            ->where('modules.status', ContentStatus::Published)
            ->orderBy('lessons.id')
            ->get(['modules.course_id as course_id', 'lessons.id as id']);

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row->course_id][] = (int) $row->id;
        }

        return array_map(fn (array $ids): Collection => collect($ids), $grouped);
    }

    /**
     * Completed required Lesson counts, keyed by enrollment.
     *
     * @param  list<int>  $enrollmentIds
     * @param  list<int>  $requiredLessonIds
     * @return array<int, int>
     */
    private function completedCountsByEnrollment(array $enrollmentIds, array $requiredLessonIds): array
    {
        if ($requiredLessonIds === []) {
            return [];
        }

        return LessonProgress::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->where('status', LessonProgressStatus::Completed)
            ->whereIn('lesson_id', $requiredLessonIds)
            ->groupBy('enrollment_id')
            ->selectRaw('enrollment_id, count(*) as aggregate')
            ->pluck('aggregate', 'enrollment_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    private function percentage(int $completed, int $total): int
    {
        if ($total === 0) {
            return 0;
        }

        return (int) round($completed / $total * 100);
    }
}
