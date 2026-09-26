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

    public function test_the_sign_in_page_is_where_a_guest_reaches_registration(): void
    {
        // Registration is deliberately not offered on the public pages, so the
        // sign in page has to carry the only route to it. Removing the home page
        // call to action is only safe while this link exists.
        $this->get('/login')
            ->assertOk()
            ->assertSee('New here?', false)
            ->assertSee(route('register'), false);

        $this->get('/register')
            ->assertOk()
            ->assertSee(route('login'), false);
    }

    public function test_password_fields_offer_a_keyboard_reachable_reveal_control(): void
    {
        // A reveal has to be a real button, not a click handler on an icon, or it
        // cannot be reached by keyboard. Its accessible name also has to change
        // with the state, because showing and hiding are different actions.
        foreach (['/login', '/register'] as $path) {
            $body = (string) $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('data-password-reveal="password"', $body);
            $this->assertStringContainsString('aria-label="Show password"', $body);
            $this->assertStringContainsString('aria-pressed="false"', $body);
            $this->assertStringContainsString('aria-controls="password"', $body);

            // The control must not submit the form it sits inside.
            $this->assertMatchesRegularExpression(
                '/<button[^>]*data-password-reveal="password"[^>]*>/',
                $body
            );
            $this->assertStringContainsString('type="button"', $body);
        }

        // Registration has two password fields, so both can be revealed.
        $register = (string) $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('data-password-reveal="password_confirmation"', $register);
    }

    public function test_the_reveal_behaviour_is_shipped_as_a_script_not_an_inline_handler(): void
    {
        // The content security policy forbids inline script, so the behaviour has
        // to live in the bundled file. A reveal that only works with scripting
        // allowed would break on every page once the policy is enforced.
        $this->assertFileExists(public_path('build/manifest.json'));

        $script = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-password-reveal', $script);
        $this->assertStringContainsString('aria-pressed', $script);
    }

    public function test_the_sign_in_page_points_students_at_their_institution_account(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Use your institution account.')
            // The hint has to be tied to the field it describes, or a screen
            // reader user hears it detached from the email input.
            ->assertSee('aria-describedby="email-hint"', false)
            ->assertSee('id="email-hint"', false);
    }

    public function test_both_forms_state_the_terms_and_the_privacy_policy(): void
    {
        foreach (['/login', '/register'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('By clicking continue, you agree to our')
                ->assertSee('Terms of Service')
                ->assertSee('Privacy Policy')
                ->assertSee(route('legal.terms'), false)
                ->assertSee(route('legal.privacy'), false);
        }
    }

    public function test_the_terms_and_privacy_pages_exist_and_are_public(): void
    {
        // A consent line that links nowhere is worse than no consent line, so the
        // destinations are pinned here and the content is checked for substance
        // rather than for a heading.
        $terms = $this->get('/terms')->assertOk();
        $terms->assertSee('What this site is')
            ->assertSee('Acceptable use')
            ->assertSee('Payments');

        $privacy = $this->get('/privacy')->assertOk();
        $privacy->assertSee('What is collected')
            ->assertSee('Who can see it')
            ->assertSee('Passwords')
            ->assertSee('payment provider');
    }
}
