<?php

namespace Tests\Feature\Ui;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Targets a thumb has to find, and a screen reader has to be told about.
 *
 * A target that is too small does not throw, does not scroll, and does not
 * overflow. It is simply hard to hit, and the only way to notice is to measure
 * the rendered box, which is what the sweep does across twelve widths.
 *
 * These tests pin the two places that were measured under the WCAG 2.5.8 AA
 * minimum of 24 by 24 pixels:
 *
 *   - a breadcrumb crumb, added with the topbar trail, at 20
 *   - an activity agenda label link, pre-existing, at 19
 *
 * Both now use row-target, which is 24 tall and grows to 44 on a coarse pointer.
 *
 * The measurement itself is in responsive-sweep.mjs. These tests cannot see the
 * cascade, so they pin the mechanism rather than the pixels, the same trade the
 * shell layout tests make.
 */
class TargetSizeTest extends TestCase
{
    use RefreshDatabase;

    private function shell(): string
    {
        $user = User::factory()->create();
        $user->profile->forceFill(['role' => UserRole::Administrator])->save();

        return (string) $this->actingAs($user->fresh())
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->getContent();
    }

    public function test_a_breadcrumb_crumb_uses_the_shared_row_target(): void
    {
        $view = (string) file_get_contents(resource_path('views/components/breadcrumbs.blade.php'));

        // row-target rather than tap. tap is 44 pixels only on a coarse pointer,
        // so on a desktop the crumb sat at its natural 20. row-target has a
        // 24 pixel floor everywhere, which is the AA line, and still grows to 44
        // for a thumb.
        $this->assertStringContainsString(
            'row-target',
            $view,
            'A breadcrumb crumb is not using row-target, so it is under the 24 pixel minimum with a mouse.'
        );
    }

    public function test_a_breadcrumb_trail_is_rendered_on_a_workspace_page(): void
    {
        $this->assertStringContainsString('aria-label="Breadcrumb"', $this->shell());
    }

    public function test_an_activity_agenda_link_uses_the_shared_row_target(): void
    {
        $view = (string) file_get_contents(resource_path('views/components/activity-agenda.blade.php'));

        $this->assertStringContainsString(
            'row-target',
            $view,
            'The activity agenda label link is not using row-target, so it is under the 24 pixel minimum.'
        );
    }

    /**
     * The agenda renders a real entry with a real destination, so the change is
     * proven on a page rather than in a file.
     */
    public function test_the_agenda_link_is_present_and_painted_with_row_target(): void
    {
        // The dashboard is behind the administrator role, so the reader has to
        // be one. A student gets a 403 and the assertion below never runs.
        $admin = User::factory()->create();
        $admin->profile->forceFill(['role' => UserRole::Administrator])->save();
        $admin = $admin->fresh();

        $target = User::factory()->create();

        ActivityLog::query()->create([
            'actor_id' => $admin->id,
            'target_user_id' => $target->id,
            // The vocabulary is role_changed, not role.changed. Guessing the
            // separator produced an enum cast failure, which is the database
            // and the cast doing their job.
            'event_type' => 'role_changed',
            'previous_role' => 'student',
            'new_role' => 'instructor',
        ]);

        $html = (string) $this->actingAs($admin)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<a\b[^>]*>/', $html, $links);

        $agendaLinks = array_values(array_filter(
            $links[0],
            static fn (string $a): bool => str_contains($a, 'row-target') && str_contains($a, 'rounded-sm')
        ));

        $this->assertNotEmpty(
            $agendaLinks,
            'No agenda link carrying row-target was rendered, so the change is not reaching the page.'
        );
    }

    /**
     * The shared classes are the reason any of this works, so they are pinned
     * rather than assumed. If a future edit drops the 24 pixel floor, the
     * measurements above become wrong and nothing here notices.
     */
    public function test_row_target_has_a_24_pixel_floor_and_grows_on_touch(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.row-target\s*\{[^}]*min-h-6/',
            $css,
            'row-target no longer has a 24 pixel floor, so every control using it drops below the AA minimum.'
        );

        $this->assertMatchesRegularExpression(
            '/@media\s*\(pointer:\s*coarse\)\s*\{[^}]*\.row-target\s*\{[^}]*min-h-11/',
            $css,
            'row-target no longer grows to 44 pixels on a touch screen.'
        );
    }

    /**
     * Footer links are a judgement call and are recorded as one.
     *
     * They are 44 pixels on a touch screen, by an existing rule, and 20 with a
     * mouse. WCAG 2.5.8 exempts a target whose size is constrained by the line
     * height of surrounding text, and these are links in a list of text. The
     * test states the position so a later change to the footer is a deliberate
     * decision rather than an accident.
     */
    public function test_footer_links_are_full_height_on_touch(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@media\s*\(pointer:\s*coarse\)\s*\{[^}]*\.footer-link\s*\{[^}]*min-h-11/',
            $css,
            'Footer links are no longer 44 pixels tall on a touch screen.'
        );
    }
}
