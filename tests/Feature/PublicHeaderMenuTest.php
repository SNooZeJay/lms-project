<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public header carries one control, the menu, so the navigation has to work
 * rather than merely exist.
 *
 * The menu is a disclosure built over real links, so the page is still navigable
 * with the script blocked. These tests cover the markup contract and the
 * destinations, because a menu that opens onto nothing is the most visible way to
 * look unfinished.
 */
class PublicHeaderMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_menu_is_a_real_disclosure(): void
    {
        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        // A button, not a div, so it is reachable and operable by keyboard.
        $this->assertStringContainsString('data-dropdown-trigger', $body);
        $this->assertStringContainsString('aria-expanded="false"', $body);
        $this->assertStringContainsString('aria-controls="public-menu"', $body);

        // The panel exists in the markup and starts closed, so it is usable
        // before the script runs and is not announced when it is shut.
        $this->assertStringContainsString('data-dropdown-panel', $body);
        $this->assertMatchesRegularExpression(
            '/data-dropdown-panel[^>]*\bhidden\b|data-dropdown-panel\s+hidden/',
            $body
        );

        $this->assertStringContainsString('Menu', $body);
    }

    public function test_the_menu_carries_courses_and_sign_in(): void
    {
        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('courses.index'), $body);
        $this->assertStringContainsString(route('login'), $body);

        /*
         | Three, not two.
         |
         | About was added between Courses and the account actions. It is a page
         | about the platform rather than a destination inside it, and somebody
         | deciding whether this is worth signing in for looks for it before they
         | look for the sign in link.
         |
         | The count is still worth pinning. Every item in the panel is a real link
         | rather than a dead control, and a number that has to be raised each time
         | is what makes somebody read the list before changing it.
         */
        $this->assertSame(
            3,
            preg_match_all('/data-dropdown-item/', $body),
            'The guest menu should carry exactly three items: Courses, About and Sign in.'
        );
        // Scoped to the header. The catalog is also linked from the hero and the
        // footer, which is fine, but inside the header it should appear once,
        // inside the menu. A second copy in a visible row is what the menu
        // replaced.
        $this->assertSame(1, preg_match('/<header\b.*?<\/header>/s', $body, $header), 'The page needs a header.');

        $this->assertSame(
            1,
            substr_count($header[0], 'href="'.route('courses.index').'"'),
            'The catalog link should appear once inside the header, inside the menu.'
        );
    }

    public function test_a_signed_in_visitor_is_not_offered_sign_in(): void
    {
        $student = User::factory()->create();
        $student->profile->forceFill(['role' => UserRole::Student])->save();

        $body = (string) $this->actingAs($student)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-dropdown-item', $body);
        $this->assertStringNotContainsString('href="'.route('login').'"', $body);
    }

    public function test_the_current_section_is_marked_in_the_menu(): void
    {
        Course::factory()->create(['status' => CourseStatus::Published]);

        $body = (string) $this->get(route('courses.index'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-current="page"', $body);
    }

    public function test_the_theme_toggle_sits_to_the_left_of_the_menu(): void
    {
        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<header\b.*?<\/header>/s', $body, $header), 'The page needs a header.');

        $theme = strpos($header[0], 'data-theme-toggle');
        $menu = strpos($header[0], 'data-dropdown-trigger');

        $this->assertNotFalse($theme, 'The header needs the theme toggle.');
        $this->assertNotFalse($menu, 'The header needs the menu button.');

        $this->assertLessThan(
            $menu,
            $theme,
            'The theme toggle should come before the menu button, so the menu is the last control in the row.'
        );
    }

    public function test_the_menu_behaviour_ships_in_the_bundled_script(): void
    {
        // The behaviour cannot live in an inline script, because the content
        // security policy forbids one. A menu that only opens with scripting
        // allowed would be shut on every page once the policy is enforced.
        $script = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('data-dropdown-trigger', $script);
        $this->assertStringContainsString('aria-expanded', $script);
        $this->assertStringContainsString("'Escape'", $script);
    }
}
