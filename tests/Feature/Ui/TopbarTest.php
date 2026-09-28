<?php

namespace Tests\Feature\Ui;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Notification;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The topbar, as the reference design puts it.
 *
 * Left: where you are. Right: search, notifications, theme, account. That is the
 * arrangement the linked Adminator build uses and the one the shell now follows.
 *
 * The parts worth pinning are the ones that were wrong before. The theme switch
 * sat in the sidebar footer, and in the drawer, and in the topbar, so on a phone
 * one screen showed the same switch twice. The left side was a flat string that
 * went nowhere. Neither was a styling preference; both were layout faults, and
 * both are measured by the probes named at the bottom of this file.
 */
class TopbarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->profile->forceFill(['role' => UserRole::Administrator])->save();

        return $user->fresh();
    }

    private function shell(string $path = '/admin', ?User $as = null): string
    {
        return (string) $this->actingAs($as ?? $this->admin())->get($path)->assertOk()->getContent();
    }

    private function topbar(string $html): string
    {
        $bar = $this->slice($html, '/<header\b.*?<\/header>/s');

        $this->assertNotEmpty($bar, 'The page has no topbar.');

        return $bar[0];
    }

    private function sidebar(string $html): string
    {
        // Bounded by the closing tag. A pattern that stops at nothing runs on
        // into the topbar and reports a topbar control as being in the sidebar,
        // which is exactly the mistake this test exists to catch.
        $aside = $this->slice($html, '/<aside\b.*?<\/aside>/s');

        return $aside[0] ?? '';
    }

    /**
     * @return list<string>
     */
    private function slice(string $html, string $pattern): array
    {
        preg_match_all($pattern, $html, $m);

        return $m[0];
    }

    /**
     * The crumb labels currently rendered, read out of the page.
     *
     * Read from the HTML rather than by calling Navigation::trail() directly,
     * because the trail is a function of the current request. Calling it outside
     * one has no route to look at, and a test that does that is testing a
     * different thing from the one a person sees.
     *
     * @return list<string>
     */
    private function crumbs(string $html): array
    {
        $bar = $this->topbar($html);

        preg_match_all('/<nav\b[^>]*aria-label="Breadcrumb".*?<\/nav>/s', $bar, $nav);

        if ($nav[0] === []) {
            return [];
        }

        preg_match_all('/<li\b[^>]*>(.*?)<\/li>/s', $nav[0][0], $items);

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim(preg_replace('/\s+/', ' ', strip_tags($item)) ?? ''),
            $items[1]
        )));
    }

    /* ------------------------------------------------------- the control set */

    public function test_the_topbar_carries_search_notifications_theme_and_account(): void
    {
        $bar = $this->topbar($this->shell());

        $this->assertStringContainsString('data-topbar-search', $bar, 'The topbar has no search.');
        $this->assertStringContainsString('Notifications', $bar, 'The topbar has no notification control.');
        $this->assertStringContainsString('data-theme-toggle', $bar, 'The topbar has no theme control.');
        $this->assertStringContainsString('data-dropdown-trigger', $bar, 'The topbar has no account menu.');
    }

    /**
     * The theme switch existed three times and now exists once.
     *
     * Sidebar footer, drawer footer, and a topbar copy that was hidden on
     * desktop. On a phone the topbar copy and the drawer copy were both visible,
     * so the same switch appeared twice on one screen. It now lives in the
     * topbar at every width, where the reference has it.
     */
    public function test_the_theme_control_appears_exactly_once_and_only_in_the_topbar(): void
    {
        $html = $this->shell();

        $this->assertSame(
            1,
            substr_count($html, 'data-theme-toggle'),
            'The theme control is rendered more than once, so one screen shows the same switch twice.'
        );

        $this->assertStringContainsString(
            'data-theme-toggle',
            $this->topbar($html),
            'The theme control is not in the topbar.'
        );

        $this->assertStringNotContainsString(
            'data-theme-toggle',
            $this->sidebar($html),
            'The theme control is still in the sidebar footer. It belongs in the topbar.'
        );
    }

    public function test_the_sidebar_footer_no_longer_holds_a_control(): void
    {
        $sidebar = $this->sidebar($this->shell());

        $this->assertNotSame('', $sidebar, 'The page has no sidebar, so this proves nothing.');

        // The navigation runs to the bottom of the sidebar now, which is what the
        // reference does. Nothing is left stranded underneath it.
        $this->assertStringNotContainsString('data-theme-toggle', $sidebar);
        $this->assertStringContainsString('workspace-nav-desktop', $sidebar);
    }

    /* ------------------------------------------------------------- the trail */

    public function test_the_topbar_shows_a_trail_rather_than_a_flat_string(): void
    {
        $bar = $this->topbar($this->shell());

        $this->assertStringContainsString(
            'aria-label="Breadcrumb"',
            $bar,
            'The topbar has no trail. It used to hold a string that went nowhere.'
        );
    }

    public function test_a_dashboard_trail_names_the_workspace_and_the_page(): void
    {
        $crumbs = $this->crumbs($this->shell());

        $this->assertSame(['Operations workspace', 'Dashboard'], $crumbs);

        // A trail of one step is not a trail, and the reference shows two.
        $this->assertGreaterThanOrEqual(2, count($crumbs));
    }

    public function test_a_page_inside_a_section_names_the_section_before_itself(): void
    {
        $crumbs = $this->crumbs($this->shell('/admin/users'));

        $this->assertContains('Users', $crumbs, 'The trail does not name the current page.');
        $this->assertSame('Operations workspace', $crumbs[0]);
    }

    /**
     * The sidebar said "you are here" on exactly one page out of all of them.
     *
     * Each navigation item declares the area it covers with a pattern such as
     * `admin.users.*`, and the answer to "is this the current page" was an exact
     * comparison against that list. A pattern is not a route name, so it never
     * equalled anything, and only the dashboard matched, because its entry has
     * no wildcard.
     *
     * The visible cost was a person on "Manage users" with no sign of where they
     * were. The cost that mattered more was silent: aria-current comes from the
     * same answer, so assistive technology was told nothing on every page except
     * the dashboard.
     */
    public function test_the_sidebar_marks_the_current_page_on_every_sub_page(): void
    {
        foreach ([
            '/admin' => 'Dashboard',
            '/admin/users' => 'Users',
            '/admin/reports' => 'Reports',
            '/admin/activity' => 'Activity log',
            '/admin/certificates' => 'Certificates',
        ] as $path => $expected) {
            $sidebar = $this->sidebar($this->shell($path));

            $this->assertSame(
                1,
                substr_count($sidebar, 'aria-current="page"'),
                "{$path} marks {$expected} as current zero or more than one times."
            );

            $this->assertMatchesRegularExpression(
                '/<a\b(?=[^>]*aria-current="page")(?=[^>]*title="'.preg_quote($expected, '/').'")[^>]*>/',
                $sidebar,
                "{$path} does not mark {$expected} as the current page."
            );
        }
    }

    public function test_the_trail_never_offers_a_link_the_server_would_refuse(): void
    {
        $bar = $this->topbar($this->shell('/admin/users'));

        preg_match_all('/<nav\b[^>]*aria-label="Breadcrumb".*?<\/nav>/s', $bar, $nav);

        preg_match_all('/<a\b[^>]*href="([^"]*)"/', $nav[0][0] ?? '', $links);

        foreach ($links[1] as $href) {
            $this->actingAs($this->admin())
                ->get($href)
                ->assertOk();
        }
    }

    /**
     * The trail is derived, so the twenty one hand written copies are gone.
     *
     * They were correct as long as the topbar had no trail of its own. The
     * moment it did, every one of those pages showed the same journey twice, and
     * the two could disagree because one was written by hand and one was
     * derived.
     */
    public function test_no_workspace_page_writes_its_own_trail_anymore(): void
    {
        $offenders = [];

        foreach (glob(resource_path('views/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                if (str_contains((string) file_get_contents($file->getPathname()), 'x-breadcrumbs')) {
                    $offenders[] = str_replace(base_path('resources/views/'), '', $file->getPathname());
                }
            }
        }

        // The public catalog page keeps its own, because the public layout has no
        // topbar to put a derived trail in.
        $offenders = array_values(array_filter(
            $offenders,
            fn (string $path): bool => ! str_contains($path, 'catalog')
                && ! str_contains($path, 'layouts')
        ));

        $this->assertSame(
            [],
            $offenders,
            'These workspace pages still write their own trail, so they show two: '.implode(', ', $offenders)
        );
    }

    /* --------------------------------------------------------- the bell count */

    public function test_the_bell_shows_the_real_unread_count(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();

        Notification::factory()->count(3)->forRecipient($admin)->ofType(NotificationType::SystemAnnouncement)->create();
        Notification::factory()->count(2)->forRecipient($admin)->read()->create();

        // The same account, or the count belongs to somebody who has none.
        $bar = $this->topbar($this->shell('/admin', $admin));

        $this->assertStringContainsString('3 unread', $bar, 'The bell does not carry the unread count.');
    }

    public function test_the_bell_count_is_never_another_accounts(): void
    {
        $admin = $this->admin();
        $stranger = User::factory()->create();

        Notification::factory()->count(7)->forRecipient($stranger)->create();

        $bar = $this->topbar($this->shell('/admin', $admin));

        $this->assertStringNotContainsString('7 unread', $bar, "The bell is showing somebody else's notices.");
        $this->assertStringContainsString('nothing unread', $bar);
    }

    public function test_the_bell_stays_put_when_there_is_nothing_unread(): void
    {
        $bar = $this->topbar($this->shell());

        // A bell that appears and vanishes with the count is a control that moves
        // under the pointer. It is always there; only the number changes.
        $this->assertStringContainsString('Notifications', $bar);
        $this->assertStringContainsString('nothing unread', $bar);
    }

    public function test_the_notification_panel_is_a_real_overlay_and_not_part_of_the_bar(): void
    {
        $bar = $this->topbar($this->shell());

        $this->assertMatchesRegularExpression(
            '/data-dropdown-panel[^>]*\bcard-raised\b[^>]*\babsolute\b|absolute[^>]*data-dropdown-panel/s',
            $bar,
            'The notification panel is not absolutely positioned, so opening it pushes the topbar out of shape.'
        );
    }

    public function test_the_notification_centre_page_exists_and_owns_only_my_notices(): void
    {
        $admin = $this->admin();
        $stranger = User::factory()->create();

        Notification::factory()->forRecipient($admin)->ofType(NotificationType::SystemAnnouncement)
            ->create(['title' => 'Mine alone']);
        Notification::factory()->forRecipient($stranger)->ofType(NotificationType::SystemAnnouncement)
            ->create(['title' => 'Somebody elses notice']);

        $html = (string) $this->actingAs($admin)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Mine alone', $html);
        $this->assertStringNotContainsString('Somebody elses notice', $html);
    }

    public function test_a_guest_cannot_reach_the_notification_centre(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }
}
