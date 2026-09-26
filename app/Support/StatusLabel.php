<?php

namespace App\Support;

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Enums\UserAccountStatus;
use BackedEnum;

/**
 * One place that turns a stored state into the words a person reads.
 *
 * Every status in the application is shown as text. Colour is added as a second
 * signal, never as the only one, so this class returns a tone that matches a
 * sentence rather than a mood.
 *
 * A Student waiting on a payment provider is not "pending" in the interface.
 * They are told they are waiting for payment confirmation, which is the phrase
 * the design system requires on the payment pages.
 */
class StatusLabel
{
    /**
     * @return array{tone: string, label: string}
     */
    public static function for(mixed $status): array
    {
        $value = $status instanceof BackedEnum ? $status->value : (string) $status;

        return match ($value) {
            // Content lifecycle
            'draft' => ['tone' => 'neutral', 'label' => 'Draft'],
            'published' => ['tone' => 'success', 'label' => 'Published'],
            'archived' => ['tone' => 'neutral', 'label' => 'Archived'],

            // Enrollment lifecycle
            'pending_payment' => ['tone' => 'warning', 'label' => 'Waiting for payment confirmation'],
            'active' => ['tone' => 'success', 'label' => 'Active'],
            'completed' => ['tone' => 'info', 'label' => 'Completed'],
            'cancelled' => ['tone' => 'neutral', 'label' => 'Cancelled'],

            // Payment lifecycle
            'pending' => ['tone' => 'warning', 'label' => 'Waiting for payment confirmation'],
            'paid' => ['tone' => 'success', 'label' => 'Payment confirmed'],
            'failed' => ['tone' => 'error', 'label' => 'Payment did not go through'],
            'refunded' => ['tone' => 'info', 'label' => 'Refunded'],

            // Certificate lifecycle
            'issued' => ['tone' => 'success', 'label' => 'Issued'],
            'revoked' => ['tone' => 'error', 'label' => 'Revoked'],

            // Lesson progress
            'not_started' => ['tone' => 'neutral', 'label' => 'Not started'],
            'in_progress' => ['tone' => 'info', 'label' => 'In progress'],

            // Quiz attempt
            'passed' => ['tone' => 'success', 'label' => 'Passed'],
            'not_passed' => ['tone' => 'warning', 'label' => 'Not passed'],
            'not_attempted' => ['tone' => 'neutral', 'label' => 'Not attempted'],

            // Account status
            'suspended' => ['tone' => 'error', 'label' => 'Suspended'],
            'verified' => ['tone' => 'success', 'label' => 'Verified'],
            'unverified' => ['tone' => 'warning', 'label' => 'Email not verified'],

            default => ['tone' => 'neutral', 'label' => self::words($value)],
        };
    }

    /**
     * The tone only, for a place that already prints its own sentence.
     */
    public static function tone(mixed $status): string
    {
        return self::for($status)['tone'];
    }

    /**
     * The words only.
     */
    public static function label(mixed $status): string
    {
        return self::for($status)['label'];
    }

    /**
     * Turn a stored enum value into sentence case.
     *
     * `pending_payment` becomes `Pending payment`, so an unrecognised state is
     * still readable instead of printing a raw database value.
     */
    public static function words(mixed $value): string
    {
        $raw = $value instanceof BackedEnum ? $value->value : (string) $value;

        return ucfirst(str_replace('_', ' ', $raw));
    }

    /**
     * Every state a given enum can hold, for a filter or a legend.
     *
     * @param  class-string<BackedEnum>  $enum
     * @return list<array{value: string, tone: string, label: string}>
     */
    public static function options(string $enum): array
    {
        /** @var list<BackedEnum> $cases */
        $cases = $enum::cases();

        return array_map(
            fn (BackedEnum $case): array => [
                'value' => (string) $case->value,
                ...self::for($case),
            ],
            $cases
        );
    }

    /**
     * Every state a given enum can hold, as `value => words`, for a select.
     *
     * A select needs the sentence form, not the tuned badge sentence, so a
     * course level reads `Beginner` rather than the `Beginner` status label.
     *
     * @param  class-string<BackedEnum>  $enum
     * @return array<string, string>
     */
    public static function selectOptions(string $enum): array
    {
        /** @var list<BackedEnum> $cases */
        $cases = $enum::cases();

        $options = [];

        foreach ($cases as $case) {
            $options[(string) $case->value] = self::words($case->value);
        }

        return $options;
    }

    /**
     * The enums whose states this class writes a specific sentence for.
     *
     * A test uses this list so a new enum cannot be added without deciding how
     * it reads in the interface.
     *
     * @return list<class-string<BackedEnum>>
     */
    public static function coveredEnums(): array
    {
        return [
            CourseStatus::class,
            ContentStatus::class,
            QuizStatus::class,
            EnrollmentStatus::class,
            PaymentStatus::class,
            CertificateStatus::class,
            LessonProgressStatus::class,
            QuizAttemptStatus::class,
            UserAccountStatus::class,
        ];
    }
}
