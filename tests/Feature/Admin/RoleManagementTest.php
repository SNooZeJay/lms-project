<?php

namespace Tests\Feature\Admin;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_search_the_user_list(): void
    {
        $administrator = $this->makeUser(UserRole::Administrator, 'administrator@example.test');
        $target = $this->makeUser(UserRole::Student, 'target@example.test', 'Target Student');

        $response = $this->actingAs($administrator)
            ->get('/admin/users?search=Target Student');

        $response->assertOk()
            ->assertSee('Target Student')
            ->assertSee('target@example.test')
            ->assertSee('Student');
    }

    public function test_administrator_can_change_a_verified_users_role_and_record_activity(): void
    {
        $administrator = $this->makeUser(UserRole::Administrator, 'administrator@example.test');
        $target = $this->makeUser(UserRole::Student, 'target@example.test');

        $response = $this->actingAs($administrator)->patch("/admin/users/{$target->id}/role", [
            'role' => UserRole::Instructor->value,
        ]);

        $response->assertRedirect('/admin/users');

        $this->assertSame(UserRole::Instructor, $target->fresh()->profile->role);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $administrator->id,
            'target_user_id' => $target->id,
            'event_type' => 'role_changed',
            'previous_role' => 'student',
            'new_role' => 'instructor',
        ]);
    }

    public function test_administrator_can_suspend_and_reactivate_an_account_with_activity_records(): void
    {
        $administrator = $this->makeUser(UserRole::Administrator, 'administrator@example.test');
        $target = $this->makeUser(UserRole::Student, 'target@example.test');

        $this->actingAs($administrator)
            ->patch("/admin/users/{$target->id}/status", ['status' => UserAccountStatus::Suspended->value])
            ->assertRedirect('/admin/users');

        $this->assertSame(UserAccountStatus::Suspended, $target->fresh()->profile->account_status);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $administrator->id,
            'target_user_id' => $target->id,
            'event_type' => 'account_status_changed',
            'previous_status' => 'active',
            'new_status' => 'suspended',
        ]);

        $this->actingAs($administrator)
            ->patch("/admin/users/{$target->id}/status", ['status' => UserAccountStatus::Active->value])
            ->assertRedirect('/admin/users');

        $this->assertSame(UserAccountStatus::Active, $target->fresh()->profile->account_status);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $administrator->id,
            'target_user_id' => $target->id,
            'event_type' => 'account_status_changed',
            'previous_status' => 'suspended',
            'new_status' => 'active',
        ]);
    }

    public function test_student_cannot_open_administrator_user_management(): void
    {
        $student = $this->makeUser(UserRole::Student, 'student@example.test');

        $this->actingAs($student)->get('/admin/users')->assertForbidden();
    }

    public function test_administrator_cannot_change_their_own_role_or_status(): void
    {
        $administrator = $this->makeUser(UserRole::Administrator, 'administrator@example.test');

        $this->actingAs($administrator)
            ->patch("/admin/users/{$administrator->id}/role", ['role' => UserRole::Student->value])
            ->assertForbidden();

        $this->actingAs($administrator)
            ->patch("/admin/users/{$administrator->id}/status", ['status' => UserAccountStatus::Suspended->value])
            ->assertForbidden();
    }

    public function test_role_change_rejects_an_unverified_target(): void
    {
        $administrator = $this->makeUser(UserRole::Administrator, 'administrator@example.test');
        $target = $this->makeUser(UserRole::Student, 'unverified@example.test');
        $target->forceFill(['email_verified_at' => null])->save();

        $response = $this->actingAs($administrator)
            ->from('/admin/users')
            ->patch("/admin/users/{$target->id}/role", ['role' => UserRole::Instructor->value]);

        $response->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Student, $target->fresh()->profile->role);
    }

    public function test_administrator_cannot_demote_or_suspend_the_final_active_administrator(): void
    {
        $administrator = $this->makeUser(UserRole::Administrator, 'administrator@example.test');

        $this->actingAs($administrator)
            ->patch("/admin/users/{$administrator->id}/role", ['role' => UserRole::Student->value])
            ->assertForbidden();

        $this->assertSame(UserRole::Administrator, $administrator->fresh()->profile->role);
        $this->assertSame(UserAccountStatus::Active, $administrator->fresh()->profile->account_status);
    }

    public function test_role_update_and_activity_record_roll_back_together(): void
    {
        $administrator = $this->makeUser(UserRole::Administrator, 'administrator@example.test');
        $target = $this->makeUser(UserRole::Student, 'target@example.test');
        ActivityLog::creating(static function (): void {
            throw new RuntimeException('Activity log failure');
        });

        try {
            $this->actingAs($administrator)
                ->patch("/admin/users/{$target->id}/role", ['role' => UserRole::Instructor->value]);
        } catch (RuntimeException) {
            // The transaction must roll back the Profile update.
        } finally {
            ActivityLog::flushEventListeners();
        }

        $this->assertSame(UserRole::Student, $target->fresh()->profile->role);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    private function makeUser(UserRole $role, string $email, string $name = 'Test User'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);

        $user->profile->forceFill([
            'role' => $role,
            'account_status' => UserAccountStatus::Active,
        ])->save();

        return $user->fresh();
    }
}
