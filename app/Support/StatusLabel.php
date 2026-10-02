<?php

namespace App\Support;

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\NotificationType;
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

            /*
             | Notice kinds.
             |
             | The `type` on a notification was stored, filtered and never read.
             | Both the topbar panel and the notification centre rendered a title
             | and nothing else, so "Exam moved to Friday" and "Your certificate
             | is ready" arrived as two unlabelled sentences to be sorted by
             | reading them.
             |
             | The sentences say what happened rather than naming the column. A
             | learner who did not pass is told "Quiz not passed" rather than
             | "Quiz failed", and a retake is "Retake available" rather than
             | "Retake required", because both are true and the second one reads
             | as a reprimand.
             |
             | The tone follows what the reader can do about it, which is what
             | the rest of this class does: a retake is a warning because there
             | is an action waiting, a revoked certificate is an error because
             | something they held has been taken away, and a lesson somebody
             | started is neutral because nothing is being asked of them.
             */
            'course_enrollment' => ['tone' => 'info', 'label' => 'Enrollment'],
            'course_content_published' => ['tone' => 'success', 'label' => 'New course content'],
            'course_completed' => ['tone' => 'success', 'label' => 'Course completed'],

            'lesson_started' => ['tone' => 'neutral', 'label' => 'Lesson started'],
            'lesson_completed' => ['tone' => 'success', 'label' => 'Lesson completed'],

            'quiz_started' => ['tone' => 'neutral', 'label' => 'Quiz started'],
            'quiz_completed' => ['tone' => 'info', 'label' => 'Quiz submitted'],
            'quiz_passed' => ['tone' => 'success', 'label' => 'Quiz passed'],
            'quiz_failed' => ['tone' => 'error', 'label' => 'Quiz not passed'],
            'retake_required' => ['tone' => 'warning', 'label' => 'Retake available'],

            'certificate_available' => ['tone' => 'success', 'label' => 'Certificate available'],
            'certificate_revoked' => ['tone' => 'error', 'label' => 'Certificate revoked'],
            'certificate_reissued' => ['tone' => 'info', 'label' => 'Certificate reissued'],

            'announcement' => ['tone' => 'info', 'label' => 'Announcement'],
            'system_announcement' => ['tone' => 'info', 'label' => 'System announcement'],

            'course_message' => ['tone' => 'info', 'label' => 'Course message'],
            'support_message' => ['tone' => 'warning', 'label' => 'Support request sent'],
            'support_reply' => ['tone' => 'success', 'label' => 'Support reply'],

            default => ['tone' => 'neutral', 'label' => self::words($value)],
        };
    }

    /**
     * How a hand-in reads.
     *
     * A separate method, and the reason is a collision that already exists in the
     * data. `pending` is a payment state as well as a submission state, and
     * `for()` is keyed on the value alone, so one of the two sentences cannot come
     * from there. A hand-in that is waiting for a lecturer is not "pending": that
     * student has finished their part and is waiting, and the interface says
     * exactly that rather than naming the column.
     *
     * @return array{tone: string, label: string}
     */
    public static function forSubmission(mixed $status): array
    {
        $value = $status instanceof BackedEnum ? $status->value : (string) $status;

        return match ($value) {
            'pending' => ['tone' => 'warning', 'label' => 'Submitted, waiting for checking'],
            'graded' => ['tone' => 'success', 'label' => 'Checked and scored'],
            'returned' => ['tone' => 'warning', 'label' => 'Handed back to be redone'],
            default => self::for($status),
        };
    }

    /**
     * How an assignment reads.
     *
     * `closed` here does not mean the brief was withdrawn. The question is still
     * readable and the answers already written against it are still marked, but
     * nothing new will be accepted. That is three ideas, and the single word
     * "Closed" carries none of them.
     *
     * @return array{tone: string, label: string}
     */
    public static function forAssignment(mixed $status): array
    {
        $value = $status instanceof BackedEnum ? $status->value : (string) $status;

        return match ($value) {
            'published' => ['tone' => 'success', 'label' => 'Open for submissions'],
            'closed' => ['tone' => 'neutral', 'label' => 'Closed to new submissions'],
            'draft' => ['tone' => 'neutral', 'label' => 'Draft, not visible to students'],
            default => self::for($status),
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
     * `NotificationType` was missing from this list and every one of its values
     * was falling through to the generic fallback, which is how a column that is
     * written, filtered and counted turned out never to be read. The list is only
     * as good as its contents, and a list nobody notices is missing an entry does
     * not notice anything by itself.
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
            NotificationType::class,
        ];
    }
}
