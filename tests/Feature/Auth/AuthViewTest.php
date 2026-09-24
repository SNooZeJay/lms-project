<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class AuthViewTest extends TestCase
{
    public function test_authentication_pages_render_accessible_forms(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('Email')
            ->assertSee('Password')
            ->assertSee('autocomplete="current-password"', false);

        $this->get('/register')
            ->assertOk()
            ->assertSee('Create student account')
            ->assertSee('Confirm password')
            ->assertSee('autocomplete="new-password"', false)
            ->assertDontSee('name="role"', false);

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Forgot password')
            ->assertSee('Email');

        $this->get('/reset-password/example-token')
            ->assertOk()
            ->assertSee('Reset password')
            ->assertSee('New password');
    }
}
