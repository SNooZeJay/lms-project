<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The icon set holds together.
 *
 * Icons are the one part of the interface where a small mistake is invisible
 * in a screenshot and obvious on the page. A name that does not resolve does
 * not throw, it quietly draws a generic mark, and the row still looks
 * finished. A drawing that arrives on its own grid looks a unit heavier than
 * its neighbour and nothing fails. A size step that exists but is never used
 * is one more value to keep coherent.
 *
 * So these tests check the things that fail quietly.
 */
class IconGeometryTest extends TestCase
{
    /** The four steps of the documented scale, and what each one is for. */
    private const SIZES = ['xs' => 'size-3.5', 'sm' => 'size-4', 'md' => 'size-5', 'lg' => 'size-6'];

    /**
     * The configured names, and the Lucide file each one resolves to.
     *
     * @return array<string, string>
     */
    private function configured(): array
    {
        return config('icons');
    }

    /**
     * The baked drawings, keyed by icon name.
     *
     * @return array<string, string>
     */
    private function drawings(): array
    {
        return require resource_path('icons/lucide.php');
    }

    /**
     * Every icon name the application actually asks for.
     *
     * A plain search for name="..." misses the three places a name is chosen at
     * runtime: the navigation data, and the default icon on the empty and error
     * states. Those are the ones that break quietly, so they are read too.
     *
     * @return list<string>
     */
    private function referenced(): array
    {
        $names = [];

        $files = array_merge(
            $this->bladeFiles(resource_path('views')),
            [base_path('app/Support/Navigation.php')],
        );

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            // A literal name attribute on the component.
            preg_match_all('/<x-icon\b[^>]*\bname="([a-z0-9-]+)"/', $source, $literal);
            $names = array_merge($names, $literal[1]);

            // An icon prop passed to a component, such as icon="shield".
            preg_match_all('/<x-[a-z-]+\b[^>]*\bicon="([a-z0-9-]+)"/', $source, $prop);
            $names = array_merge($names, $prop[1]);

            // An icon inside a data array, such as ['icon' => 'award'].
            preg_match_all("/'icon'\s*=>\s*'([a-z0-9-]+)'/", $source, $array);
            $names = array_merge($names, $array[1]);

            // The navigation builds items through a helper, and the icon is its
            // third argument: self::item('Label', 'route', 'award', [...]).
            preg_match_all("/self::item\(\s*'[^']*'\s*,\s*'[^']*'\s*,\s*'([a-z0-9-]+)'/", $source, $nav);
            $names = array_merge($names, $nav[1]);
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(string $directory): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    public function test_every_referenced_icon_resolves_to_a_drawing(): void
    {
        $drawings = $this->drawings();
        $unresolved = [];

        foreach ($this->referenced() as $name) {
            if (! array_key_exists($name, $drawings)) {
                $unresolved[] = $name;
            }
        }

        // Without this, a typo or a removed icon draws the fallback mark and the
        // page still looks finished, so the mistake ships.
        $this->assertSame(
            [],
            $unresolved,
            'These icon names are asked for but have no drawing: '.implode(', ', $unresolved)
        );
    }

    public function test_the_configured_names_are_all_used(): void
    {
        $referenced = $this->referenced();
        $unused = array_values(array_diff(array_keys($this->configured()), $referenced));

        // An icon nothing renders is dead weight, and it reads as coverage when
        // someone later looks for a drawing that is not there.
        $this->assertSame(
            [],
            $unused,
            'These icons are configured but never rendered: '.implode(', ', $unused)
        );
    }

    public function test_the_baked_drawings_match_the_configuration(): void
    {
        $configured = $this->configured();
        $drawings = $this->drawings();

        // A stale bake is invisible: the page renders the old drawings and
        // config/icons.php says something else. `php artisan icons:sync --check`
        // reports the same thing.
        $this->assertSame(
            array_keys($configured),
            array_keys($drawings),
            'resources/icons/lucide.php is out of date. Run: php artisan icons:sync'
        );
    }

    public function test_the_icon_set_has_no_duplicate_names(): void
    {
        $source = (string) file_get_contents(config_path('icons.php'));

        preg_match_all("/^\s*'([a-z0-9-]+)'\s*=>/m", $source, $matches);

        $duplicates = array_keys(array_filter(array_count_values($matches[1]), fn (int $n): bool => $n > 1));

        // A duplicate key is silently discarded by the array literal, so the icon
        // simply stops resolving with nothing failing.
        $this->assertSame([], $duplicates, 'These icon names are defined more than once: '.implode(', ', $duplicates));
    }

    public function test_every_drawing_shares_one_canvas_and_stroke(): void
    {
        $component = (string) file_get_contents(resource_path('views/components/icon.blade.php'));

        // One wrapper, one grid, one weight. These are what make a row of icons
        // look like a set; the drawings inside are the part that can drift.
        $this->assertSame(1, substr_count($component, 'viewBox="0 0 24 24"'), 'Every icon must share one viewBox.');
        $this->assertSame(1, substr_count($component, 'stroke-width="2"'), 'The stroke weight must be set once, on the wrapper.');

        foreach ($this->drawings() as $name => $drawing) {
            // A stroke width on an individual drawing would override the wrapper
            // and is the usual way an icon ends up heavier than its neighbours.
            $this->assertStringNotContainsString(
                'stroke-width',
                $drawing,
                "The {$name} drawing overrides the shared stroke weight."
            );

            // The baked shape must not carry its own wrapper, because the
            // component supplies one and two nested svgs render at nothing.
            $this->assertStringNotContainsString('<svg', $drawing, "The {$name} drawing kept its own svg wrapper.");
            $this->assertStringNotContainsString('</svg>', $drawing, "The {$name} drawing kept its own svg wrapper.");
        }
    }

    public function test_the_reveal_icons_are_both_available(): void
    {
        $drawings = $this->drawings();

        // The password field swaps between these two on every press. If either
        // is missing the toggle has nothing to show, and the fallback mark would
        // make both states look identical, which is worse than no toggle.
        foreach (['eye', 'eye-off'] as $name) {
            $this->assertArrayHasKey($name, $drawings, "The {$name} drawing is required by the password reveal.");
            $this->assertNotSame('', trim($drawings[$name]));
        }

        // They must actually differ, or pressing the control changes nothing.
        $this->assertNotSame(
            trim($drawings['eye']),
            trim($drawings['eye-off']),
            'The two reveal drawings are identical, so the toggle would show no change.'
        );
    }

    public function test_the_size_scale_is_exactly_the_documented_steps(): void
    {
        $component = (string) file_get_contents(resource_path('views/components/icon.blade.php'));

        foreach (self::SIZES as $name => $class) {
            $this->assertStringContainsString(
                "'{$name}' => '{$class}'",
                $component,
                "The {$name} size step must map to {$class}."
            );
        }

        // Any other step is a value nothing uses, and the next person to add an
        // icon has no way to know which one to reach for.
        preg_match_all("/'(xs|sm|md|lg|xl|2xl)' => '(size-[0-9.]+)'/", $component, $found, PREG_SET_ORDER);

        $this->assertCount(
            count(self::SIZES),
            $found,
            'The size scale has grown a step that is not documented: '.implode(', ', array_column($found, 1))
        );
    }

    public function test_an_icon_renders_the_requested_name(): void
    {
        $response = $this->get('/login');

        $response->assertOk();

        // The email and lock marks are the two the sign in page leads with, and
        // the reveal toggle sits beside the lock. If the component stopped
        // resolving names, these three would all draw the same fallback.
        $html = (string) $response->getContent();

        preg_match_all('/<svg[^>]*viewBox="0 0 24 24"[^>]*>(.*?)<\/svg>/s', $html, $drawings);

        $this->assertGreaterThan(2, count($drawings[1]), 'The sign in page should draw several icons.');

        // Every drawing must have real content. An empty shape is invisible and
        // renders as a blank space where an icon should be.
        foreach ($drawings[1] as $index => $inner) {
            $this->assertNotSame('', trim($inner), "Icon drawing {$index} on the sign in page is empty.");
        }

        // And they must not all be the same drawing, which is exactly what a
        // fallback produces: a page where every icon is the neutral mark.
        $this->assertGreaterThan(
            1,
            count(array_unique(array_map('trim', $drawings[1]))),
            'Every icon on the sign in page rendered the same drawing, so names are not resolving.'
        );
    }
}
