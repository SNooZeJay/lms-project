<?php

namespace App\Support;

use App\Enums\CourseType;
use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Works out what a Student should be told about a payment, and what they can do
 * about it.
 *
 * This exists because the raw enrollment and payment statuses are not something
 * a Student can act on. "pending_payment" does not say whether money is coming,
 * whether the attempt was declined, or whether the checkout simply expired while
 * nobody was looking. A declined card in particular must never be reported as
 * something an administrator has to fix, because it is the Student's to retry.
 */
final class StudentPaymentState
{
    /**
     * A checkout the Student never finished. The provider will not honour it
     * indefinitely, so after this long it is treated as expired rather than
     * pending, which would otherwise wait forever.
     */
    private const EXPIRY_HOURS = 24;

    /**
     * The tone names a status colour in the design token set, so a view can
     * hand the value straight to a badge or a note without translating it.
     */
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $message,
        public readonly string $tone,
        public readonly bool $offersPayment,
        public readonly ?string $actionLabel = null,
    ) {}

    public static function for(Enrollment $enrollment, ?Payment $payment, ?Course $course): self
    {
        $isPaidCourse = $course !== null && $course->course_type === CourseType::Paid;

        if ($enrollment->grantsAccess()) {
            return self::settled($isPaidCourse);
        }

        if (! $isPaidCourse) {
            return new self(
                key: 'not_available',
                label: 'Not available',
                message: 'This enrollment does not open the course yet. Contact an administrator for help.',
                tone: 'neutral',
                offersPayment: false,
            );
        }

        $action = self::payAction($course);

        return match (true) {
            $payment === null => new self(
                key: 'awaiting_payment',
                label: 'Awaiting payment',
                message: 'Enroll to continue to payment. The course opens once the payment is confirmed.',
                tone: 'warning',
                offersPayment: true,
                actionLabel: $action,
            ),

            $payment->status === PaymentStatus::Failed => new self(
                key: 'failed',
                label: 'Payment failed',
                message: 'The payment was not completed, so nothing was charged. You can try again.',
                tone: 'error',
                offersPayment: true,
                actionLabel: 'Try payment again',
            ),

            $payment->status === PaymentStatus::Refunded => new self(
                key: 'refunded',
                label: 'Refunded',
                message: 'This payment was refunded, so the course is closed. You can buy it again.',
                tone: 'neutral',
                offersPayment: true,
                actionLabel: 'Pay again',
            ),

            $payment->status === PaymentStatus::Cancelled => new self(
                key: 'cancelled',
                label: 'Payment cancelled',
                message: 'The payment was cancelled, so nothing was charged. You can try again.',
                tone: 'neutral',
                offersPayment: true,
                actionLabel: 'Try payment again',
            ),

            self::hasExpired($payment) => new self(
                key: 'expired',
                label: 'Payment expired',
                message: 'The checkout was left unfinished and can no longer be paid. Start a new one.',
                tone: 'error',
                offersPayment: true,
                actionLabel: 'Start payment again',
            ),

            default => new self(
                key: 'awaiting_payment',
                label: 'Awaiting payment',
                message: 'The payment is being confirmed. The course opens as soon as it arrives.',
                tone: 'warning',
                offersPayment: true,
                actionLabel: $action,
            ),
        };
    }

    private static function settled(bool $isPaidCourse): self
    {
        return $isPaidCourse
            ? new self(
                key: 'paid',
                label: 'Paid',
                message: 'Payment confirmed. Open the course to read its published lessons.',
                tone: 'success',
                offersPayment: false,
            )
            : new self(
                key: 'included',
                label: 'Included',
                message: 'Open the course to read its published lessons.',
                tone: 'success',
                offersPayment: false,
            );
    }

    private static function hasExpired(Payment $payment): bool
    {
        $createdAt = $payment->created_at;

        if (! $createdAt instanceof Carbon) {
            return false;
        }

        return $createdAt->lt(now()->subHours(self::EXPIRY_HOURS));
    }

    /**
     * The checkout button always names the exact amount from the course record.
     *
     * The amount is formatted through `Money`, so the stored integer minor
     * units are never divided into a float and never printed raw. A Student is
     * told what they will be charged before they start, and a tampered browser
     * value cannot change it.
     */
    private static function payAction(Course $course): string
    {
        return 'Pay '.Money::format($course->price_minor, $course->currency);
    }
}
