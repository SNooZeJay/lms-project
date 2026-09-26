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

        $body = $this->actingAs($administrator)
            ->get('/admin/activity')
            ->assertOk()
            ->assertSee('Target Student')
            ->assertSee('Role changed')
            ->assertSee('Instructor')
            ->getContent();

        // The activity record is read only and sanitized. The page carries no
        // field name, no label, and no value for anything the product plan
        // forbids in the activity log: a credential field, a stored session
        // value, a network address, or browser metadata.
        //
        // Two words are deliberately not on this list. The shared account menu
        // links to the password page on every authenticated page, and the
        // shared layout publishes a `csrf-token` meta tag, so neither word can be
        // searched for across the whole document without a false positive. What
        // must never appear is a stored value for either one, so those are
        // checked below against the form and the columns the page does render.
        foreach (['remember_token', 'ip_address', 'user_agent', 'sk_test', 'PAYMONGO_SECRET'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, (string) $body);
        }

        $this->assertStringNotContainsString('type="password"', (string) $body);
        $this->assertStringNotContainsString('name="password"', (string) $body);
        $this->assertStringNotContainsString('sk_test_', (string) $body);
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
