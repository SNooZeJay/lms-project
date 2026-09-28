<?php

namespace App\Actions\Completion;

use App\Enums\CertificateStatus;
use App\Enums\EnrollmentStatus;
use App\Events\CourseCompleted;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseRequirement;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Certificates\CertificateCodeGenerator;
use App\Services\Learning\CourseCompletionChecker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Completes a Course and issues exactly one certificate.
 *
 * Eligibility comes from the stored records only. Issuance is idempotent: a
 * second call returns the existing certificate instead of creating a second
 * one, and the unique (enrollment, course) rule makes a duplicate impossible.
 */
class CompleteCourse
{
    public function __construct(
        private readonly CourseCompletionChecker $checker,
        private readonly CertificateCodeGenerator $codes,
    ) {}

    public function handle(User $actor, Enrollment $enrollment): array
    {
        Gate::forUser($actor)->authorize('complete', $enrollment);

        $requirements = $this->checker->requirementsFor($enrollment->course);

        if (! $requirements->certificate_enabled) {
            throw ValidationException::withMessages([
                'course' => 'This course does not issue certificates.',
            ]);
        }

        $result = $this->checker->check($enrollment);

        if (! $result['eligible']) {
            throw ValidationException::withMessages([
                'course' => $result['reasons'],
            ]);
        }

        return DB::transaction(function () use ($actor, $enrollment, $requirements): array {
            $existing = $enrollment->certificate()->first();

            if ($existing !== null) {
                return ['enrollment' => $enrollment->refresh(), 'certificate' => $existing, 'created' => false];
            }

            $enrollment->forceFill([
                'status' => EnrollmentStatus::Completed,
                'completed_at' => now(),
            ]);
            $enrollment->save();

            $certificate = $this->issue($actor, $enrollment, $requirements);

            /*
             | Only on the path that completed it.
             |
             | The early return above is somebody asking a second time for a
             | course they already finished, and the certificate is already in
             | their hands. Telling them again is the noise the specification
             | rules out, and the report already says created: false for a caller
             | that cares.
             |
             | The certificate id travels with the event rather than being looked
             | up by a listener, so a listener cannot read it before the
             | transaction that created it has committed.
             */
            CourseCompleted::dispatch($enrollment, $enrollment->course, $certificate->id);

            return ['enrollment' => $enrollment->refresh(), 'certificate' => $certificate, 'created' => true];
        });
    }

    private function issue(User $actor, Enrollment $enrollment, CourseRequirement $requirements): Certificate
    {
        $certificate = new Certificate;

        try {
            $certificate->forceFill([
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'course_id' => $enrollment->course_id,
                'certificate_code' => $this->codes->unique(),
                'student_name_snapshot' => (string) $enrollment->student->name,
                'course_title_snapshot' => (string) $enrollment->course->title,
                'completion_date' => now()->toDateString(),
                'issued_by' => $actor->id,
                'replaces_certificate_id' => null,
                'status' => CertificateStatus::Issued,
                'active_slot' => 1,
            ]);
            $certificate->save();
        } catch (QueryException $exception) {
            $existing = $enrollment->certificate()->first();

            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }

        return $certificate;
    }
}
