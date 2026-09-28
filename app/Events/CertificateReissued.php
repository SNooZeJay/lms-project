<?php

namespace App\Events;

use App\Models\Certificate;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A replacement certificate was issued for a withdrawn one.
 *
 * The replacement is the subject, not the original, because the original is
 * revoked and a link to it lands on a page that explains why it is gone. The
 * dedup key names the original so a second reissue of the same one is refused.
 *
 * Implements ShouldDispatchAfterCommit. See StudentEnrolled for why.
 */
class CertificateReissued implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Certificate $original,
        public readonly Certificate $replacement,
    ) {}
}
