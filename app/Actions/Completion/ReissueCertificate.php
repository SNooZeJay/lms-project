<?php

namespace App\Actions\Completion;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\User;
use App\Services\Certificates\CertificateCodeGenerator;
use App\Services\Learning\CourseCompletionChecker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Reissue creates a linked replacement only after fresh validation.
 */
class ReissueCertificate
{
    public function __construct(
        private readonly CourseCompletionChecker $checker,
        private readonly CertificateCodeGenerator $codes,
    ) {}

    public function handle(User $actor, Certificate $revoked): Certificate
    {
        Gate::forUser($actor)->authorize('reissue', $revoked);

        if ($revoked->status !== CertificateStatus::Revoked) {
            throw ValidationException::withMessages([
                'certificate' => 'Only a revoked certificate can be reissued.',
            ]);
        }

        $enrollment = $revoked->enrollment;

        $result = $this->checker->check($enrollment);

        if (! $result['eligible']) {
            throw ValidationException::withMessages([
                'certificate' => $result['reasons'],
            ]);
        }

        return DB::transaction(function () use ($actor, $enrollment, $revoked): Certificate {
            $replacement = new Certificate;

            try {
                $replacement->forceFill([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'course_id' => $enrollment->course_id,
                    'certificate_code' => $this->codes->unique(),
                    'student_name_snapshot' => (string) $enrollment->student->name,
                    'course_title_snapshot' => (string) $enrollment->course->title,
                    'completion_date' => now()->toDateString(),
                    'issued_by' => $actor->id,
                    'replaces_certificate_id' => $revoked->id,
                    'status' => CertificateStatus::Issued,
                    'active_slot' => 1,
                ]);
                $replacement->save();
            } catch (QueryException $exception) {
                $existing = Certificate::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('status', CertificateStatus::Issued)
                    ->first();

                if ($existing === null) {
                    throw $exception;
                }

                return $existing;
            }

            return $replacement;
        });
    }
}
