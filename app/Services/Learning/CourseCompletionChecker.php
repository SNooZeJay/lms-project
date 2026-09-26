<?php

namespace App\Services\Learning;

use App\Enums\ContentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\QuizStatus;
use App\Models\Course;
use App\Models\CourseRequirement;
use App\Models\Enrollment;

/**
 * The single definition of "has this Student finished this Course".
 *
 * Everything is read from stored records. Nothing is ever taken from a request.
 */
class CourseCompletionChecker
{
    /**
     * @return array{
     *     eligible: bool,
     *     reasons: list<string>,
     *     lessons_total: int,
     *     lessons_completed: int,
     *     quizzes_total: int,
     *     quizzes_passed: int
     * }
     */
    public function check(Enrollment $enrollment): array
    {
        $course = $enrollment->course;
        $requirements = $this->requirementsFor($course);

        $lessonIds = $this->requiredLessonIds($course, $requirements);
        $lessonsCompleted = $this->completedLessonCount($enrollment, $lessonIds);

        $quizIds = $course->quizzes()
            ->where('quizzes.status', QuizStatus::Published)
            ->when($requirements->require_required_quizzes, fn ($query) => $query->where('is_required', true))
            ->pluck('id')
            ->all();

        $quizzesPassed = $enrollment->quizAttempts()
            ->whereIn('quiz_id', $quizIds)
            ->when($requirements->require_passing_score, fn ($query) => $query->where('passed', true))
            ->distinct()
            ->count('quiz_id');

        $reasons = [];

        if ($requirements->require_all_lessons && $lessonsCompleted < count($lessonIds)) {
            $reasons[] = 'Finish every required published lesson.';
        }

        if ($requirements->minimum_lesson_percent !== null) {
            $percent = count($lessonIds) > 0
                ? ($lessonsCompleted / count($lessonIds)) * 100
                : 100.0;

            if ($percent < (float) $requirements->minimum_lesson_percent) {
                $reasons[] = 'Reach the required lesson percentage.';
            }
        }

        if ($requirements->require_required_quizzes && $quizzesPassed < count($quizIds)) {
            $reasons[] = 'Pass every required published quiz.';
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'lessons_total' => count($lessonIds),
            'lessons_completed' => $lessonsCompleted,
            'quizzes_total' => count($quizIds),
            'quizzes_passed' => $quizzesPassed,
        ];
    }

    /**
     * The requirements row, created with safe defaults on first use.
     */
    public function requirementsFor(Course $course): CourseRequirement
    {
        $existing = CourseRequirement::query()->find($course->id);

        if ($existing !== null) {
            return $existing;
        }

        $requirements = new CourseRequirement;
        $requirements->forceFill([
            'course_id' => $course->id,
            'require_all_lessons' => true,
            'require_required_quizzes' => true,
            'require_passing_score' => true,
            'certificate_enabled' => true,
        ]);
        $requirements->save();

        return $requirements;
    }

    /**
     * @return array<int, int>
     */
    private function requiredLessonIds(Course $course, CourseRequirement $requirements): array
    {
        return $course->lessons()
            ->where('lessons.status', ContentStatus::Published)
            ->when(
                $requirements->require_all_lessons,
                fn ($query) => $query->where('lessons.is_required', true),
            )
            ->pluck('lessons.id')
            ->all();
    }

    /**
     * @param  array<int, int>  $lessonIds
     */
    private function completedLessonCount(Enrollment $enrollment, array $lessonIds): int
    {
        if ($lessonIds === []) {
            return 0;
        }

        return $enrollment->lessonProgress()
            ->whereIn('lesson_id', $lessonIds)
            ->where('status', LessonProgressStatus::Completed)
            ->count();
    }
}
