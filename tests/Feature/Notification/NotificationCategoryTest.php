<?php

namespace Tests\Feature\Notification;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Support\StatusLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A notice says what kind of notice it is.
 *
 * The stored `type` is the one fact about a notification that was never shown
 * anywhere. Both the topbar panel and the notification centre rendered a title,
 * a body and a time, so "Exam moved to Friday", "Your certificate is ready" and
 * "You have a new message" arrived as three unlabelled sentences that a reader
 * had to sort by reading them.
 *
 * The reference this was checked against carries a category line on every
 * notification row. That idea is taken; their category, a subject code, is not,
 * because this application has no such field. The `type` column is the same idea
 * and it has been stored all along.
 *
 * The reference also shows who caused each notice. This application cannot: the
 * table has `user_id`, which is the recipient, and no column for the person who
 * triggered it. Rendering a sender would mean a migration and an audit of every
 * writer, which is a change of the data model rather than a change of a panel.
 * So that part is deliberately not done, and saying so is better than inventing
 * a sender.
 *
 * The test for the sentences is the interesting one. `StatusLabel::coveredEnums`
 * already exists to force a new enum to decide how it reads, but its test only
 * checks that a label is not empty, and an undecided value falls through to a
 * generic sentence and passes. So this asserts that every notification type reads
 * differently from the mechanical fallback, which is what "a decision was made"
 * actually means.
 */
class NotificationCategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Exactly how every notice must read.
     *
     * An earlier version of this test asserted that no label equals the
     * mechanical fallback, on the theory that a coincidence proved no decision
     * had been made. It failed on `course_completed`, where the sentence that was
     * actually written for it is also what the fallback produces. That is not a
     * defect in the sentence. "Course completed" is what the rest of the
     * application calls that state, and a deliberately different wording would
     * have been worse English for the sake of satisfying a test.
     *
     * So the specification is written out instead. That is the form the design
     * system actually wants: every stored state has one agreed sentence, and the
     * test is where that agreement is recorded.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function expectedReadings(): array
    {
        return [
            'course_enrollment' => ['Enrollment', 'info'],
            'course_content_published' => ['New course content', 'success'],
            'course_completed' => ['Course completed', 'success'],

            'lesson_started' => ['Lesson started', 'neutral'],
            'lesson_completed' => ['Lesson completed', 'success'],

            'quiz_started' => ['Quiz started', 'neutral'],
            'quiz_completed' => ['Quiz submitted', 'info'],
            'quiz_passed' => ['Quiz passed', 'success'],
            'quiz_failed' => ['Quiz not passed', 'error'],
            'retake_required' => ['Retake available', 'warning'],

            'certificate_available' => ['Certificate available', 'success'],
            'certificate_revoked' => ['Certificate revoked', 'error'],
            'certificate_reissued' => ['Certificate reissued', 'info'],

            'announcement' => ['Announcement', 'info'],
            'system_announcement' => ['System announcement', 'info'],

            'course_message' => ['Course message', 'info'],
            'support_message' => ['Support request sent', 'warning'],
            'support_reply' => ['Support reply', 'success'],
        ];
    }

    public function test_every_notification_type_reads_as_the_agreed_sentence(): void
    {
        $expected = static::expectedReadings();

        // The table is the specification, so a type added to the enum without a
        // sentence fails here rather than quietly reading as its own column name.
        $this->assertCount(
            count(NotificationType::cases()),
            $expected,
            'A notification type was added or removed without deciding how it reads. Add or remove '
            .'its row in the specification and in StatusLabel together.'
        );

        foreach (NotificationType::cases() as $type) {
            [$words, $tone] = $expected[$type->value];

            $this->assertSame(
                $words,
                StatusLabel::label($type),
                $type->value.' reads as "'.StatusLabel::label($type).'" rather than the agreed "'.$words.'".'
            );

            $this->assertSame(
                $tone,
                StatusLabel::tone($type),
                $type->value.' carries the '.$tone.' tone it should, so colour and wording agree.'
            );
        }
    }

    /** The wording is the interesting part, so it is quoted rather than derived. */
    public function test_the_wording_says_what_happened_rather_than_naming_a_column(): void
    {
        // "Failed" is a verdict on a person. What happened is that a quiz was not
        // passed, and a retake is waiting.
        $this->assertSame('Quiz not passed', StatusLabel::label(NotificationType::QuizFailed));
        $this->assertSame('Retake available', StatusLabel::label(NotificationType::RetakeRequired));

        // A support request the reader sent is not the same event as the reply
        // they are waiting for, and one label for both would hide which is which.
        $this->assertSame('Support request sent', StatusLabel::label(NotificationType::SupportMessage));
        $this->assertSame('Support reply', StatusLabel::label(NotificationType::SupportReply));
    }

    public function test_the_notification_types_are_covered_by_the_label_helper(): void
    {
        $this->assertContains(
            NotificationType::class,
            StatusLabel::coveredEnums(),
            'The notification type is stored state that the interface reads, and it is not on the '
            .'list that makes a new enum decide how it reads.'
        );
    }

    /**
     * Both places a notice is listed have to carry the sentence, not just the
     * helper that produces it.
     *
     * A label nobody renders is the same as no label, so this asserts the panel
     * and the centre separately. They are separate surfaces: the panel is five
     * rows in a dropdown, the centre is the full history, and either could have
     * been left alone.
     */
    public function test_the_topbar_panel_labels_each_notice(): void
    {
        $user = User::factory()->create();

        $this->noticeFor($user, NotificationType::QuizFailed, 'Your quiz was not passed');

        $this->actingAs($user)
            ->get(route('conversations.index'))
            ->assertOk()
            ->assertSee('Your quiz was not passed')
            ->assertSee('Quiz not passed');
    }

    public function test_the_notification_centre_labels_each_notice(): void
    {
        $user = User::factory()->create();

        $this->noticeFor($user, NotificationType::CertificateAvailable, 'Your certificate is ready');

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Your certificate is ready')
            ->assertSee('Certificate available');
    }

    private function noticeFor(User $user, NotificationType $type, string $title): void
    {
        $notice = new Notification;
        $notice->forceFill([
            'user_id' => $user->id,
            'type' => $type->value,
            'title' => $title,
            'body' => 'Some detail about it.',
            'link' => route('notifications.index'),
        ])->save();
    }

    public function test_a_notice_about_a_certificate_does_not_claim_to_be_a_lesson(): void
    {
        $this->assertSame('Certificate available', StatusLabel::label(NotificationType::CertificateAvailable));
        $this->assertSame('Quiz not passed', StatusLabel::label(NotificationType::QuizFailed));
        $this->assertSame('Retake available', StatusLabel::label(NotificationType::RetakeRequired));
    }
}
