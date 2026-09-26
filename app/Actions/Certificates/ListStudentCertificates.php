<?php

namespace App\Actions\Certificates;

use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\CourseCompletionChecker;
use Illuminate\Support\Collection;

/**
 * The Student certificate list, with a live completion summary per course.
 */
class ListStudentCertificates
{
    public function __construct(private readonly CourseCompletionChecker $checker) {}

    /**
     * @return Collection<int, array{certificate: Certificate|null, course: Course, summary: array<string, mixed>}>
     */
    public function handle(User $actor): Collection
    {
        $enrollments = Enrollment::query()
            ->where('student_id', $actor->id)
            ->whereIn('status', [
                EnrollmentStatus::Active,
                EnrollmentStatus::Completed,
            ])
            ->with('course')
            ->orderByDesc('activated_at')
            ->orderByDesc('id')
            ->get();

        return $enrollments->map(function ($enrollment): array {
            return [
                'enrollment' => $enrollment,
                'course' => $enrollment->course,
                'certificate' => $enrollment->certificate()->first(),
                'summary' => $this->checker->check($enrollment),
            ];
        });
    }
}
