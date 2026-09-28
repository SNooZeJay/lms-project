<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * That confirming an address says so.
 *
 * The gap this covers was invisible to every test that already existed, and the
 * reason is worth recording. Following the link marked the address verified and
 * redirected to /email/verify?verified=1, and Fortify answers a visit from
 * somebody who has already verified with a redirect away from that page. So the
 * person landed on their profile, which is a fine page, and the account was
 * genuinely verified. Nothing was broken, every assertion about the stored record
 * passed, and no one was told that the thing they had just done had worked.
 *
 * So the assertion that matters is not that the record changed. It is that the
 * person is told, on a page that exists for it, with a way onward.
 */
class EmailVerificationConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_following_the_link_lands_on_a_confirmation_rather_than_silently_moving_on(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('verification.confirmed', ['verified' => 1]));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_confirmation_says_the_address_is_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('verification.confirmed', ['verified' => 1]))
            ->assertOk()
            ->assertSee('Your address is confirmed');
    }

    /**
     * The page told people to go and check their inbox.
     *
     * It inherited the wording of the unverified notice, so the last thing read
     * before entering the product was an instruction to do the thing that had just
     * been done. The assertion is that none of that wording is present.
     */
    public function test_the_confirmation_does_not_tell_anybody_to_go_and_check_their_inbox(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('verification.confirmed', ['verified' => 1]))
            ->assertOk()
            ->assertDontSee('Check your spam folder')
            ->assertDontSee('Open the link before continuing');
    }

    /**
     * The confirmation is only useful with somewhere to go.
     *
     * The countdown is an enhancement and is in the markup as data, so this
     * asserts the thing a person relies on: a real link to their own workspace,
     * named, that works with scripting unavailable.
     */
    public function test_the_confirmation_offers_a_way_onward_to_the_readers_own_workspace(): void
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        $this->actingAs($student)
            ->get(route('verification.confirmed'))
            ->assertOk()
            ->assertSee(route('student.dashboard'), escape: false);

        $this->actingAs($instructor)
            ->get(route('verification.confirmed'))
            ->assertOk()
            ->assertSee(route('instructor.dashboard'), escape: false);
    }

    /**
     * The countdown, read as the attributes it is.
     *
     * Asserted as data rather than as behaviour because the behaviour is a timer,
     * and a test that waits eight seconds to watch a browser navigate proves the
     * same thing far more slowly. What matters is that the page publishes how long
     * it will wait, that the wait is changeable from the markup rather than
     * hard coded in the script, and that the status line is a polite live region
     * so the change is announced rather than only seen.
     */
    public function test_the_countdown_is_published_accessibly_and_configured_from_the_markup(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('verification.confirmed'));

        $response->assertOk();
        $response->assertSee('data-auto-continue', escape: false);
        $response->assertSee('data-seconds="8"', escape: false);
        $response->assertSee('aria-live="polite"', escape: false);
        $response->assertSee('<noscript>', escape: false);

        // Polite, not assertive. A page that moves on its own is disorienting but it
        // is not an emergency, and it should not interrupt whatever is being read.
        $response->assertDontSee('aria-live="assertive"', escape: false);
    }

    /**
     * The timer can be stopped, and stopping it is permanent.
     *
     * A control that undoes itself is worse than no control, because a person who
     * presses it and then finds the page has gone anyway will not press it next
     * time.
     */
    public function test_the_countdown_offers_a_way_to_stop_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('verification.confirmed'))
            ->assertOk()
            ->assertSee('data-auto-continue-cancel', escape: false)
            ->assertSee('Stay here');
    }

    /**
     * The page congratulates somebody on a verified address, so it must not be
     * reachable by somebody whose address is not.
     *
     * It cannot happen through the link, which verifies before redirecting. This
     * makes it impossible rather than unlikely, and covers a hand written address
     * as well.
     */
    public function test_the_confirmation_is_closed_to_an_unverified_account(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.confirmed'))
            ->assertRedirect('/email/verify');
    }

    public function test_the_confirmation_is_closed_to_a_signed_out_reader(): void
    {
        $this->get(route('verification.confirmed'))->assertRedirect(route('login'));
    }

    /**
     * The redirect is bound rather than configured, and this is why.
     *
     * Three places read config('fortify.redirects.email-verification'): this
     * redirect, the resend, and the notice. Pointing that one value at the
     * confirmation would send somebody who has just asked for another email to a
     * page congratulating them on an address they have not confirmed. The resend
     * has to keep going to the notice, and this asserts that it does.
     */
    public function test_asking_for_another_email_still_goes_to_the_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from('/email/verify')
            ->post(route('verification.send'))
            ->assertRedirect('/email/verify');
    }

    /**
     * The unverified notice is unchanged, and still says what it should.
     *
     * The confirmed state was moved to its own address rather than added as a
     * second state here, so this is the guard that the split did not cost the
     * original page anything.
     */
    public function test_the_unverified_notice_still_asks_for_the_link(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/email/verify')
            ->assertOk()
            ->assertSee('Verify your email')
            ->assertSee('Open the link before continuing to your account.')
            ->assertSee('Resend verification email')
            ->assertDontSee('Your address is confirmed');
    }
}
