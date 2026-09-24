<?php

namespace Tests\Feature\Auth;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_student_user_and_profile(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Student Example',
            'email' => 'student@example.test',
            'password' => 'a secure student password',
            'password_confirmation' => 'a secure student password',
        ]);

        $response->assertRedirect('/email/verify');

        $user = User::where('email', 'student@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Student, $user->profile->role);
        $this->assertSame(UserAccountStatus::Active, $user->profile->account_status);
        $this->assertFalse($user->profile->must_change_password);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_rejects_privileged_fields(): void
    {
        $response = $this->post('/register', [
            'name' => 'Unsafe Student',
            'email' => 'unsafe@example.test',
            'password' => 'a secure student password',
            'password_confirmation' => 'a secure student password',
            'role' => 'administrator',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'unsafe@example.test']);
        $this->assertGuest();
    }
}
