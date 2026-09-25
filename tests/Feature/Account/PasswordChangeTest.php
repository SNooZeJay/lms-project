<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_temporary_password_is_redirected_to_the_change_page(): void
    {
        $user = User::factory()->create();
        $user->profile->forceFill([
            'must_change_password' => true,
        ])->save();

        $this->actingAs($user)
            ->get('/account/profile')
            ->assertRedirect('/account/password');

        $this->actingAs($user)
            ->get('/account/password')
            ->assertOk()
            ->assertSee('Change your password');
    }

    public function test_password_change_requires_the_current_password_and_clears_the_gate(): void
    {
        $user = User::factory()->create();
        $user->profile->forceFill([
            'must_change_password' => true,
        ])->save();

        $this->actingAs($user)
            ->post('/account/password', [
                'current_password' => 'wrong password',
                'password' => 'a new secure password',
                'password_confirmation' => 'a new secure password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->post('/account/password', [
                'current_password' => 'password',
                'password' => 'a new secure password',
                'password_confirmation' => 'a new secure password',
            ])
            ->assertRedirect('/student');

        $this->assertTrue(Hash::check('a new secure password', $user->fresh()->password));
        $this->assertFalse($user->fresh()->profile->must_change_password);
    }
}
