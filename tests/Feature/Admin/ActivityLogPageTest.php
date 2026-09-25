<?php

namespace Tests\Feature\Admin;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_read_role_activity_but_not_edit_it(): void
    {
        $administrator = $this->makeAdministrator();
        $target = $this->makeStudent();

        ActivityLog::create([
            'actor_id' => $administrator->id,
            'target_user_id' => $target->id,
            'event_type' => 'role_changed',
            'previous_role' => UserRole::Student,
            'new_role' => UserRole::Instructor,
            'previous_status' => null,
            'new_status' => null,
        ]);

        $this->actingAs($administrator)
            ->get('/admin/activity')
            ->assertOk()
            ->assertSee('Target Student')
            ->assertSee('Role changed')
            ->assertSee('Instructor');

        $this->actingAs($administrator)
            ->get('/admin/activity')
            ->assertDontSee('password', false);
    }

    private function makeAdministrator(): User
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->profile->forceFill([
            'role' => UserRole::Administrator,
            'account_status' => UserAccountStatus::Active,
        ])->save();

        return $user->fresh();
    }

    private function makeStudent(): User
    {
        $user = User::factory()->create([
            'name' => 'Target Student',
            'email' => 'target@example.test',
        ]);
        $user->profile->forceFill([
            'role' => UserRole::Student,
            'account_status' => UserAccountStatus::Active,
        ])->save();

        return $user->fresh();
    }
}
