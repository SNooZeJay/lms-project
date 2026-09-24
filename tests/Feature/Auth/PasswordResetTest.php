<?php

namespace Tests\Feature\Auth;

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
