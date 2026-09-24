<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_is_sent_to_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/account/profile');

        $response->assertRedirect('/email/verify');
    }

    public function test_verified_user_can_open_the_account_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/account/profile');

        $response->assertOk();
        $response->assertSee($user->name);
    }
}
