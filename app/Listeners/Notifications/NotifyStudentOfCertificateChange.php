<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\NotificationType;
use App\Events\CertificateReissued;
use App\Events\CertificateRevoked;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Tells a student that a certificate was withdrawn or replaced.
 *
 * A revoked certificate is a withdrawal, and the person who earned it is entitled
 * to know rather than find out. The reason is included: an administrator records
 * one, and a notice that says only "revoked" would leave the student with a
 * question the system could have answered.
 *
 * The reissue links to the replacement, not the original. The original is
 * revoked, so a link to it lands on a page that explains why it is gone, which is
 * a poor thing to send somebody who has just been told their replacement is
 * ready.
 */
class NotifyStudentOfCertificateChange
{
    public function __construct(private readonly RecordNotification $notify) {}

    public function handleRevoked(CertificateRevoked $event): void
    {
        $certificate = $event->certificate;
        $course = $certificate->course;
        $reason = $certificate->revocation_reason;

        $body = "Your certificate for \"{$course->title}\" has been withdrawn.";

        if (is_string($reason) && trim($reason) !== '') {
            $body .= ' Reason: '.trim($reason).'.';
        }

        $this->notify->handle(
            $certificate->student,
            NotificationType::CertificateRevoked,
            'Your certificate was withdrawn',
            $body,
            course: $course,
            /*
             | Suffixed, because a certificate can be revoked more than once over
             | its life across a reissue and a further withdrawal, and each of
             | those is a separate fact the student has to be told.
             */
            dedupKey: "certificate:{$certificate->id}:revoked",
            link: route('student.certificates.index'),
            authorizeLink: fn (User $who): bool => Gate::forUser($who)->allows('viewAny', Certificate::class),
            subjectType: 'certificate',
            subjectId: $certificate->id,
        );
    }

    public function handleReissued(CertificateReissued $event): void
    {
        $replacement = $event->replacement;
        $course = $replacement->course;

        $this->notify->handle(
            $replacement->student,
            NotificationType::CertificateReissued,
            'Your replacement certificate is ready',
            "A replacement certificate for \"{$course->title}\" has been issued.",
            course: $course,
            // Keyed on the original, because the act being reported is replacing
            // that one and a second replacement of the same original is the same
            // event however many rows it would write.
            dedupKey: "certificate:{$event->original->id}:reissued",
            link: route('student.certificates.show', $replacement),
            authorizeLink: fn (User $who): bool => Gate::forUser($who)->allows('view', $replacement),
            subjectType: 'certificate',
            subjectId: $replacement->id,
        );
    }
}
