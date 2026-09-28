<?php

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\MarkAllNotificationsRead;
use App\Actions\Notifications\MarkNotificationRead;
use App\Enums\NotificationType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Read state belongs to the recipient and to nobody else.
 *
 * These drive the real routes rather than calling the actions directly, because
 * the question is not only whether the action refuses but whether the route
 * refuses. A check that lives only in a policy is one forgotten middleware away
 * from being decorative, and a check that lives only in a controller is one
 * forgotten caller away from the same.
 */
class MarkNotificationReadTest extends TestCase
{
    use RefreshDatabase;

    private function noticeFor(User $user, bool $read = false): Notification
    {
        return Notification::factory()
            ->forRecipient($user)
            ->ofType(NotificationType::SystemAnnouncement)
            ->create(['read_at' => $read ? now() : null]);
    }

    /* ------------------------------------------------------------------ owner */

    public function test_the_recipient_can_mark_their_own_notice_read(): void
    {
        $student = User::factory()->create();
        $notice = $this->noticeFor($student);

        $this->actingAs($student)
            ->from('/student')
            ->patch(route('notifications.read', $notice))
            ->assertRedirect('/student');

        $this->assertNotNull($notice->fresh()->read_at);
    }

    public function test_marking_an_already_read_notice_again_is_not_an_error(): void
    {
        $student = User::factory()->create();
        $notice = $this->noticeFor($student, read: true);
        $first = $notice->read_at;

        $this->actingAs($student)
            ->from('/student')
            ->patch(route('notifications.read', $notice))
            ->assertRedirect();

        $this->assertTrue($first->equalTo($notice->fresh()->read_at), 'A second mark must not move the timestamp.');
    }

    public function test_the_action_reports_whether_it_was_the_call_that_changed_anything(): void
    {
        $student = User::factory()->create();
        $notice = $this->noticeFor($student);
        $action = new MarkNotificationRead;

        $this->assertTrue($action->handle($student, $notice));
        $this->assertFalse($action->handle($student, $notice));
    }

    /* ---------------------------------------------------------------- refusal */

    public function test_another_account_cannot_mark_a_notice_read(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $notice = $this->noticeFor($owner);

        $this->actingAs($stranger)
            ->from('/student')
            ->patch(route('notifications.read', $notice))
            ->assertForbidden();

        $this->assertNull($notice->fresh()->read_at, 'A refused request must leave the notice unread.');
    }

    public function test_an_administrator_cannot_mark_somebody_elses_notice_read(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->profile->forceFill(['role' => UserRole::Administrator])->save();
        $notice = $this->noticeFor($owner);

        $this->actingAs($admin)
            ->from('/admin')
            ->patch(route('notifications.read', $notice))
            ->assertForbidden();

        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_a_suspended_account_cannot_mark_a_notice_read(): void
    {
        $owner = User::factory()->create();
        $owner->profile->forceFill(['account_status' => UserAccountStatus::Suspended])->save();
        $notice = $this->noticeFor($owner);

        // The account.active middleware refuses a suspended account before the
        // route is ever reached, so this is a redirect and not a 403.
        $this->actingAs($owner)
            ->from('/student')
            ->patch(route('notifications.read', $notice))
            ->assertRedirect();

        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_a_guest_cannot_reach_the_route(): void
    {
        $owner = User::factory()->create();
        $notice = $this->noticeFor($owner);

        $this->patch(route('notifications.read', $notice))->assertRedirect(route('login'));

        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_a_notice_that_does_not_exist_is_a_404_not_a_crash(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->from('/student')
            ->patch(route('notifications.read', 999999))
            ->assertNotFound();
    }

    /* -------------------------------------------------------------- mark all */

    public function test_marking_everything_read_touches_only_the_askers_own_notices(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $mineUnread = collect(range(1, 4))->map(fn (): Notification => $this->noticeFor($mine));
        $mineAlreadyRead = $this->noticeFor($mine, read: true);
        $theirUnread = collect(range(1, 3))->map(fn (): Notification => $this->noticeFor($theirs));

        $this->actingAs($mine)
            ->from('/student')
            ->patch(route('notifications.read-all'))
            ->assertRedirect('/student');

        foreach ($mineUnread as $notice) {
            $this->assertNotNull($notice->fresh()->read_at);
        }

        $this->assertTrue($mineAlreadyRead->read_at->equalTo($mineAlreadyRead->fresh()->read_at));

        foreach ($theirUnread as $notice) {
            $this->assertNull($notice->fresh()->read_at, 'Marking all read must not cross accounts.');
        }
    }

    public function test_marking_everything_read_reports_how_many_it_changed(): void
    {
        $student = User::factory()->create();
        collect(range(1, 6))->map(fn (): Notification => $this->noticeFor($student));
        $this->noticeFor($student, read: true);

        $this->assertSame(6, (new MarkAllNotificationsRead)->handle($student));
    }

    public function test_marking_everything_read_twice_changes_nothing_the_second_time(): void
    {
        $student = User::factory()->create();
        collect(range(1, 3))->map(fn (): Notification => $this->noticeFor($student));
        $action = new MarkAllNotificationsRead;

        $this->assertSame(3, $action->handle($student));
        $this->assertSame(0, $action->handle($student));
    }

    public function test_a_guest_cannot_mark_everything_read(): void
    {
        $student = User::factory()->create();
        $notice = $this->noticeFor($student);

        $this->patch(route('notifications.read-all'))->assertRedirect(route('login'));

        $this->assertNull($notice->fresh()->read_at);
    }

    /* ----------------------------------------------------------------- badge */

    public function test_the_badge_reflects_read_state_for_the_signed_in_account_only(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();

        $this->noticeFor($student);
        $this->noticeFor($student);
        $this->noticeFor($student, read: true);
        $this->noticeFor($other);

        $this->assertSame(2, Notification::unreadCountFor($student));
        $this->assertSame(1, Notification::unreadCountFor($other));
    }
}
