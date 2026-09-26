<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The one place that decides which Lesson a Student should return to.
 *
 * The record is server-owned. Nothing is read from a request, and a Lesson is
 * only offered while the Student can actually open it right now.
 */
class ContinueLearning
{
    /**
     * The most recently viewed readable Lesson progress row, or null.
     */
    public static function forStudent(User $student): ?LessonProgress
    {
        if ($student->profile?->role !== UserRole::Student) {
            return null;
        }

        $candidates = LessonProgress::query()
            ->where('student_id', $student->id)
            ->whereNotNull('last_viewed_at')
            ->whereHas('enrollment', fn ($query) => $query
                ->where('student_id', $student->id)
                ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed]))
            ->whereHas('lesson', fn ($query) => $query
                ->where('lessons.status', ContentStatus::Published)
                ->whereHas('module', fn ($moduleQuery) => $moduleQuery
                    ->where('modules.status', ContentStatus::Published)
                    ->whereHas('course', fn ($courseQuery) => $courseQuery
                        ->where('courses.status', CourseStatus::Published))))
            ->orderByDesc('last_viewed_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        foreach ($candidates as $progress) {
            if (Gate::forUser($student)->allows('viewForStudent', $progress->lesson)) {
                return $progress;
            }
        }

        return null;
    }
}
