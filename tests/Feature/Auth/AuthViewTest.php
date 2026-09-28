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
            ->assertSee('Create your account')
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

    public function test_the_authentication_pages_do_not_expose_project_or_implementation_detail(): void
    {
        // These are the two pages every visitor sees first. A person signing in
        // does not need to be told what the site is built with, and the wording
        // that used to do so read as documentation rather than a product.
        foreach (['/login', '/register'] as $path) {
            $body = (string) $this->get($path)->assertOk()->getContent();

            foreach ([
                'BSIT',
                'Laravel',
                'Blade',
                'academic project',
                'student project',
                'school project',
                'Instructors and Administrators are assigned',
                'Assigned by an Administrator',
            ] as $phrase) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $phrase,
                    $body,
                    "{$path} should not mention '{$phrase}'."
                );
            }
        }
    }

    public function test_the_authentication_pages_do_not_over_explain(): void
    {
        // Each page carries a heading, one supporting line, the form, and the
        // legal line. Anything longer than that is a paragraph nobody asked for.
        foreach (['/login', '/register'] as $path) {
            $body = (string) $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('Learn IT. Build practical skills.', $body);

            foreach ([
                'keeps a student',
                'one record',
                'course record',
                'stay tied to',
            ] as $phrase) {
                $this->assertStringNotContainsStringIgnoringCase($phrase, $body);
            }
        }
    }

    public function test_the_role_name_is_not_exposed_in_the_public_registration_form(): void
    {
        // The form does create a learner account, but naming the role in the
        // public interface makes it read as an administrative system.
        $body = (string) $this->get('/register')->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('student account', $body);
        $this->assertStringNotContainsString('Instructor and Administrator access is assigned', $body);
    }

    public function test_the_sign_in_page_is_where_a_guest_reaches_registration(): void
    {
        // Registration is deliberately not offered on the public pages, so the
        // sign in page has to carry the only route to it. Removing the home page
        // call to action is only safe while this link exists.
        $this->get('/login')
            ->assertOk()
            ->assertSee('New here?', false)
            ->assertSee('Create account', false)
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

    public function test_the_forms_do_not_claim_an_institution_account_is_required(): void
    {
        // Registration accepts any unique email address, so telling a visitor to
        // use an institution account would be false advice. If that ever becomes
        // a real rule, this test is the thing to update with it.
        foreach (['/login', '/register'] as $path) {
            $body = (string) $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('institution', $body);
            $this->assertStringNotContainsString('edu.ph', $body);
        }
    }

    public function test_both_forms_state_the_terms_and_the_privacy_policy(): void
    {
        // The line names the action being taken, so it reads correctly on the
        // page it appears on rather than sounding the same on both.
        foreach (['/login' => 'signing in', '/register' => 'creating an account'] as $path => $action) {
            $this->get($path)
                ->assertOk()
                ->assertSee("By {$action}, you agree to our")
                ->assertSee('Terms of Service')
                ->assertSee('Privacy Policy')
                ->assertSee(route('legal.terms'), false)
                ->assertSee(route('legal.privacy'), false);
        }
    }

    public function test_the_brand_panel_carries_the_copyright_and_nothing_else(): void
    {
        // The reference design closes its brand panel with the product name and a
        // year. The wording must stay that plain: no framework, no course, no
        // build location, which is what the reference file says in its place.
        foreach (['/login', '/register'] as $path) {
            $body = (string) $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString(date('Y').' IT Learning Hub', $body);

            foreach (['BUILT IN', 'RIGA', 'v3.1 preview'] as $phrase) {
                $this->assertStringNotContainsString($phrase, $body);
            }
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
