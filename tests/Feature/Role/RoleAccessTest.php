<?php

namespace Tests\Feature\Role;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_open_only_its_landing_page(): void
    {
        $student = $this->makeUser(UserRole::Student, 'student@example.test');
        $instructor = $this->makeUser(UserRole::Instructor, 'instructor@example.test');
        $administrator = $this->makeUser(UserRole::Administrator, 'administrator@example.test');

        $this->actingAs($student)->get('/student')->assertOk();
        $this->actingAs($student)->get('/instructor')->assertForbidden();
        $this->actingAs($student)->get('/admin')->assertForbidden();

        $this->actingAs($instructor)->get('/instructor')->assertOk();
        $this->actingAs($instructor)->get('/admin')->assertForbidden();

        $this->actingAs($administrator)->get('/admin')->assertOk();
    }

    public function test_login_redirects_to_the_landing_page_for_each_role(): void
    {
        $student = $this->makeUser(UserRole::Student, 'student-login@example.test');
        $instructor = $this->makeUser(UserRole::Instructor, 'instructor-login@example.test');
        $administrator = $this->makeUser(UserRole::Administrator, 'administrator-login@example.test');

        $this->post('/login', ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect('/student');
        $this->post('/logout');

        $this->post('/login', ['email' => $instructor->email, 'password' => 'password'])
            ->assertRedirect('/instructor');
        $this->post('/logout');

        $this->post('/login', ['email' => $administrator->email, 'password' => 'password'])
            ->assertRedirect('/admin');
    }

    public function test_suspended_user_session_is_blocked_and_ended(): void
    {
        $user = $this->makeUser(UserRole::Student, 'suspended@example.test');
        $user->profile->forceFill([
            'account_status' => UserAccountStatus::Suspended,
        ])->save();

        $response = $this->actingAs($user)->get('/student');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_unverified_user_is_sent_to_verification_before_role_page(): void
    {
        $user = User::factory()->unverified()->create();
        $user->profile->forceFill([
            'role' => UserRole::Student,
            'account_status' => UserAccountStatus::Active,
        ])->save();

        $this->actingAs($user)->get('/student')->assertRedirect('/email/verify');
    }

    private function makeUser(UserRole $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->profile->forceFill([
            'role' => $role,
            'account_status' => UserAccountStatus::Active,
        ])->save();

        return $user->fresh();
    }
}
