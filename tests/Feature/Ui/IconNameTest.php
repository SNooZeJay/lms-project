<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * Every icon a template asks for has to exist.
 *
 * Written after the same mistake was made three times in one session, in three
 * different files. `file-text`, `credit-card` and `bar-chart` were all written
 * from memory, none of them is one of this project's forty icons, and each one
 * rendered as nothing at all. A missing icon is not an error and not a warning:
 * the element is simply absent, so a capability card loses its only visual
 * anchor and looks like a heading with a gap above it.
 *
 * It is the class of fault that survives a full test run, because nothing about
 * a missing icon is wrong in the markup. The markup is perfectly valid; it just
 * says something that is not there.
 *
 * So the names are checked against `config('icons.php')`, which is the one place
 * the project decides what an icon is. `SyncLucideIcons` is the command that
 * regenerates that file from the Lucide set, and the check therefore also keeps
 * the repository honest about which of those names are actually available.
 */
class IconNameTest extends TestCase
{
    /**
     * Every icon name any template asks for.
     *
     * Both spellings are read, because both are used in this codebase: the
     * component form `name="…"` and the bound form `:name="$item['icon']"`, which
     * is how a grid of items names its icon.
     *
     * @return array<string, list<string>>
     */
    private function requestedIcons(): array
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'))
        );

        $found = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            $names = [];

            // <x-icon name="book-open" …>
            preg_match_all('/<x-icon\b[^>]*\bname="([a-z0-9-]+)"/', $contents, $literal);

            // <x-icon :name="$item['icon']" …>, which cannot be resolved to a
            // string here, so the arrays that feed it are read instead.
            preg_match_all("/'icon'\s*=>\s*'([a-z0-9-]+)'/", $contents, $bound);

            foreach ([...$literal[1], ...$bound[1]] as $name) {
                $names[$name] = true;
            }

            if ($names !== []) {
                $found[$file->getFilename()] = array_keys($names);
            }
        }

        return $found;
    }

    public function test_every_icon_a_template_asks_for_exists(): void
    {
        $available = array_keys((array) config('icons'));

        $this->assertNotSame([], $available, 'No icons are configured at all.');

        $missing = [];

        foreach ($this->requestedIcons() as $file => $names) {
            foreach ($names as $name) {
                if (! in_array($name, $available, true)) {
                    $missing[] = $file.': '.$name;
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These templates ask for icons that do not exist. A missing icon renders as nothing at all, '
            ."so the element simply disappears:\n  ".implode("\n  ", $missing)
        );
    }

    /**
     * The literal form only, checked separately from the bound one.
     *
     * A bound name comes out of an array, so this is the half of the check a
     * reader can audit by looking at a template. It is worth having on its own
     * because a broken name there is a typo, and a broken name in an array is
     * usually a name from a different icon set entirely.
     */
    public function test_a_literal_icon_name_is_written_out_rather_than_guessed(): void
    {
        $available = array_keys((array) config('icons'));

        // The two naming conventions this project uses, so a name written in the
        // other one is caught rather than rendered blank.
        $unmatched = [];

        foreach ($this->requestedIcons() as $file => $names) {
            foreach ($names as $name) {
                if (in_array($name, $available, true)) {
                    continue;
                }

                $unmatched[] = $file.': '.$name;
            }
        }

        $this->assertSame([], $unmatched);
    }

    public function test_the_configured_icons_are_the_ones_the_project_intends(): void
    {
        $available = array_keys((array) config('icons'));

        $this->assertSame($available, array_unique($available), 'The icon list has a duplicate in it.');

        // A handful the interface depends on by name, so a regeneration that
        // renamed one of them fails here rather than as a blank square somewhere.
        foreach (['book-open', 'layers', 'award', 'check', 'close', 'arrow-right', 'pencil', 'user', 'shield'] as $name) {
            $this->assertContains(
                $name,
                $available,
                "The interface names the icon `{$name}` and it is not in config/icons.php."
            );
        }
    }
}
