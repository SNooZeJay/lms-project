<?php

namespace App\Actions\Completion;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Revocation records who did it and why. Nothing is deleted.
 */
class RevokeCertificate
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Certificate $certificate, array $data): Certificate
    {
        Gate::forUser($actor)->authorize('revoke', $certificate);

        if ($certificate->status === CertificateStatus::Revoked) {
            throw ValidationException::withMessages([
                'certificate' => 'This certificate is already revoked.',
            ]);
        }

        return DB::transaction(function () use ($certificate, $data): Certificate {
            // Free the active slot so a reissue can claim it again.
            $certificate->refresh();
            $certificate->forceFill([
                'status' => CertificateStatus::Revoked,
                'revoked_at' => now(),
                'revocation_reason' => $data['reason'] ?? null,
                'active_slot' => null,
            ]);
            $certificate->save();

            return $certificate;
        });
    }
}
