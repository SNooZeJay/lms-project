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

        $existing = $this->findExisting($actor, $course);

        if ($existing !== null) {
            $this->reuseExisting($existing, $course);

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
     * Decide what an existing enrollment means when the Student enrolls again.
     *
     * A free course must already grant access, otherwise something is wrong and
     * the Student is told to contact an administrator.
     *
     * A paid course is different. An enrollment on pending_payment means the
     * Student already started a payment, so it is reused and the checkout can
     * be tried again. A cancelled one means the payment was refunded, so it
     * returns to pending_payment and the course may be bought again.
     *
     * @throws ValidationException
     */
    private function reuseExisting(Enrollment $enrollment, Course $course): void
    {
        if ($course->course_type === CourseType::Free) {
            $this->ensureItGrantsAccess($enrollment);

            return;
        }

        if ($enrollment->status === EnrollmentStatus::Cancelled) {
            $enrollment->forceFill([
                'status' => EnrollmentStatus::PendingPayment,
                'cancelled_at' => null,
                'activated_at' => null,
            ]);
            $enrollment->save();
        }
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
        // A paid course does not grant access on enrollment. It waits for a
        // verified payment event, which is the only thing allowed to activate
        // it. A free course has nothing to wait for.
        $isFree = $course->course_type === CourseType::Free;

        try {
            return DB::transaction(function () use ($actor, $course, $isFree): Enrollment {
                $enrollment = new Enrollment;
                $enrollment->forceFill([
                    'student_id' => $actor->id,
                    'course_id' => $course->id,
                    'status' => $isFree ? EnrollmentStatus::Active : EnrollmentStatus::PendingPayment,
                    'activated_at' => $isFree ? now() : null,
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

            $this->reuseExisting($existing, $course);

            return $existing;
        }
    }
}
