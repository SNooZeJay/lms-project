<?php

namespace App\Actions\Enrollment;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EnrollStudent
{
    public function handle(User $actor, Course $course): Enrollment
    {
        Gate::forUser($actor)->authorize('create', [Enrollment::class, $course]);

        abort_unless($course->status === CourseStatus::Published, 404);

        if ($course->course_type !== CourseType::Free) {
            throw ValidationException::withMessages([
                'course' => 'Paid enrollment opens in a later release.',
            ]);
        }

        $existing = $this->findExisting($actor, $course);

        if ($existing !== null) {
            $this->ensureItGrantsAccess($existing);

            return $existing;
        }

        return $this->createEnrollment($actor, $course);
    }

    private function findExisting(User $actor, Course $course): ?Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $actor->id)
            ->where('course_id', $course->id)
            ->first();
    }

    /**
     * @throws ValidationException
     */
    private function ensureItGrantsAccess(Enrollment $enrollment): void
    {
        if (! $enrollment->grantsAccess()) {
            throw ValidationException::withMessages([
                'course' => 'This enrollment is not active. Contact an administrator for help.',
            ]);
        }
    }

    private function createEnrollment(User $actor, Course $course): Enrollment
    {
        try {
            return DB::transaction(function () use ($actor, $course): Enrollment {
                $enrollment = new Enrollment;
                $enrollment->forceFill([
                    'student_id' => $actor->id,
                    'course_id' => $course->id,
                    'status' => EnrollmentStatus::Active,
                    'activated_at' => now(),
                ]);
                $enrollment->save();

                return $enrollment;
            });
        } catch (QueryException $exception) {
            // The unique rule is the final guard against a double submit.
            $existing = $this->findExisting($actor, $course);

            if ($existing === null) {
                throw $exception;
            }

            $this->ensureItGrantsAccess($existing);

            return $existing;
        }
    }
}
