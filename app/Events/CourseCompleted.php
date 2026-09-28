<?php

namespace App\Events;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A student finished a course, and a certificate was issued for it.
 *
 * Both facts travel together because the Action does both in one transaction:
 * CompleteCourse issues the certificate in the same transaction as completion,
 * so "eligible" is never a separately observable state. Two events would allow a
 * listener to be told about a completion that had no certificate, or a
 * certificate for a completion that did not happen.
 *
 * The certificate id is nullable because the Action reports whether it created
 * one, and a course with unmet requirements completes without a certificate.
 * That is a real outcome and not an error, so the event says so rather than
 * carrying a null that a listener has to guess about.
 *
 * Implements ShouldDispatchAfterCommit. See StudentEnrolled for why.
 */
class CourseCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Course $course,
        public readonly ?int $certificateId = null,
    ) {}
}
