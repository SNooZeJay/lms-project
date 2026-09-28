<?php

namespace App\Events;

use App\Models\Certificate;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A certificate was withdrawn.
 *
 * A revoked certificate is a withdrawal and the student who earned it is entitled
 * to know, so the notice goes to them rather than to whoever pressed the button.
 *
 * Implements ShouldDispatchAfterCommit. See StudentEnrolled for why.
 */
class CertificateRevoked implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Certificate $certificate) {}
}
