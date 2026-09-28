<?php

namespace Tests\Feature\Auth;

use App\Http\Responses\SafePasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_link_uses_the_local_mailer_without_enumeration(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'learner@example.test',
        ]);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_uses_the_same_safe_response(): void
    {
        Notification::fake();

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'missing@example.test',
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('status');
        $response->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    }

    /**
     * The message must be word for word the same either way.
     *
     * Asserting only that a status session key exists is not enough, because
     * both branches of Fortify's controller set one. That assertion passed while
     * the two branches carried different sentences, and two different sentences
     * are enough to tell a stranger which addresses have an account here.
     *
     * This is what actually matters once real mail is configured, because
     * "we emailed your link" against "if an account exists" is a list of who
     * studies here, published to anyone who types an address into a form.
     */
    public function test_the_message_never_reveals_whether_the_account_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'learner@example.test',
        ]);

        $known = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $unknown = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'missing@example.test',
        ]);

        // The flashed message, with its exact text. Comparing the whole page
        // instead would trip over the form repopulating itself with the address
        // that was typed, which is the one difference that is not a leak.
        $expected = SafePasswordResetLinkResponse::MESSAGE;

        $known->assertSessionHas('status', $expected);
        $unknown->assertSessionHas('status', $expected);

        $this->assertDoesNotMatchRegularExpression(
            '/(we have|we\'ve) emailed|has been sent to your|successfully/i',
            $expected,
            'The message states the outcome instead of leaving it unstated.'
        );
    }

    /** The same comparison, for a caller that wants JSON rather than a redirect. */
    public function test_a_json_caller_also_cannot_tell_the_two_apart(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'learner@example.test',
        ]);

        $known = $this->postJson('/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/forgot-password', ['email' => 'missing@example.test']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame(
            $known->getContent(),
            $unknown->getContent(),
            'The JSON answer differs between an address with an account and one without.'
        );
    }

    /**
     * The password confirmation page has to exist and work.
     *
     * The route is registered as soon as any Fortify view is enabled, so it is
     * reachable by typing its address whether or not anything links to it. The
     * response class it returns is an interface that only gets bound when
     * confirmPasswordView is called, which nothing here called, so the container
     * tried to build an interface and every signed-in person who opened the page
     * got a 500.
     *
     * A registered route that cannot render is a dead control, and this project
     * has exactly the actions password confirmation exists to protect: role
     * changes, account suspension, certificate revocation, and payments.
     */
    public function test_the_password_confirmation_page_renders_for_a_signed_in_user(): void
    {
        $user = User::factory()->create(['email' => 'learner@example.test']);

        $response = $this->actingAs($user)->get('/user/confirm-password');

        $response->assertOk();
        $response->assertSee('Confirm your password');
    }

    /** A guest has no password to confirm, so the page sends them to sign in. */
    public function test_a_guest_is_sent_away_from_password_confirmation(): void
    {
        $this->get('/user/confirm-password')->assertRedirect('/login');
    }

    /** The correct password confirms, and the person lands where they meant to go. */
    public function test_the_correct_password_confirms_and_returns_to_the_intended_page(): void
    {
        $user = User::factory()->create([
            'email' => 'learner@example.test',
            'password' => Hash::make('a known password'),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['url.intended' => url('/account/profile')])
            ->post('/user/confirm-password', ['password' => 'a known password']);

        $response->assertRedirect('/account/profile');
        $response->assertSessionHasNoErrors();

        $this->assertNotNull(
            session('auth.password_confirmed_at'),
            'A correct password did not mark the session as confirmed.'
        );
    }

    /**
     * A wrong password must confirm nothing, and must not say which half of the
     * pair was wrong.
     */
    public function test_a_wrong_password_is_refused_without_confirming(): void
    {
        $user = User::factory()->create([
            'email' => 'learner@example.test',
            'password' => Hash::make('a known password'),
        ]);

        $response = $this->actingAs($user)
            ->from('/user/confirm-password')
            ->post('/user/confirm-password', ['password' => 'not the password']);

        $response->assertRedirect('/user/confirm-password');
        $response->assertSessionHasErrors('password');

        $this->assertNull(
            session('auth.password_confirmed_at'),
            'A wrong password still marked the session as confirmed.'
        );
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'learner@example.test',
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a new secure password',
            'password_confirmation' => 'a new secure password',
        ]);

        $response->assertRedirect('/account/profile');
        $this->assertTrue(Hash::check('a new secure password', $user->fresh()->password));
    }
}
