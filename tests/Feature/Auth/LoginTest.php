<?php

namespace Tests\Feature\Auth;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_verified_user_can_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'learner@example.test',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/account/profile');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_fail_without_revealing_account_state(): void
    {
        $user = User::factory()->create([
            'email' => 'learner@example.test',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong password value',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.test',
        ]);
        $user->profile->forceFill([
            'account_status' => UserAccountStatus::Suspended,
        ])->save();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_repeated_invalid_logins_are_throttled(): void
    {
        User::factory()->create([
            'email' => 'throttle@example.test',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'throttle@example.test',
                'password' => 'wrong password value',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => 'throttle@example.test',
            'password' => 'wrong password value',
        ]);

        $response->assertTooManyRequests();
        $this->assertGuest();
    }
}
