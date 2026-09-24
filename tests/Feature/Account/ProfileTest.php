<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_and_update_approved_profile_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
        ]);

        $this->actingAs($user)
            ->get('/account/profile')
            ->assertOk()
            ->assertSee('Original Name');

        $this->actingAs($user)
            ->patch('/account/profile', [
                'name' => 'Updated Name',
                'bio' => 'A short learner biography.',
            ])
            ->assertRedirect('/account/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'bio' => 'A short learner biography.',
        ]);
    }

    public function test_profile_update_rejects_privileged_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/profile', [
            'name' => 'Updated Name',
            'bio' => 'A short learner biography.',
            'email' => 'changed@example.test',
            'role' => 'administrator',
            'account_status' => 'suspended',
            'must_change_password' => true,
        ]);

        $response->assertSessionHasErrors(['email', 'role', 'account_status', 'must_change_password']);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'role' => 'student',
            'account_status' => 'active',
            'must_change_password' => false,
        ]);
    }
}
