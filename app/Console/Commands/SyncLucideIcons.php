<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Bakes the configured Lucide drawings into a plain PHP map.
 *
 * The application renders icons as inline SVG, which is what lets a drawing
 * inherit the surrounding text colour and scale with the layout. That means the
 * drawing has to be present at request time, but node_modules is a build time
 * dependency and is not deployed. So this command copies the inner markup of
 * each configured icon out of the package and into resources/icons/lucide.php,
 * which is committed.
 *
 * The result is checked in on purpose. A deployed server can render every icon
 * without npm ever running, and a reviewer can read exactly which drawings the
 * product uses without installing a package first.
 */
class SyncLucideIcons extends Command
{
    protected $signature = 'icons:sync
                            {--check : Report what would change and exit non zero if the baked file is stale, without writing}';

    protected $description = 'Bake the configured Lucide drawings into resources/icons/lucide.php';

    /** Where the npm package lands. */
    private const PACKAGE = 'node_modules/lucide-static/icons';

    public function handle(): int
    {
        $map = config('icons');

        if (! is_array($map) || $map === []) {
            $this->components->error('config/icons.php returned no icons.');

            return self::FAILURE;
        }

        $package = base_path(self::PACKAGE);

        if (! File::isDirectory($package)) {
            $this->components->error('The lucide-static package is not installed. Run: npm install');

            return self::FAILURE;
        }

        $drawings = [];
        $missing = [];
        $malformed = [];

        foreach ($map as $name => $file) {
            $path = $package.'/'.$file.'.svg';

            if (! File::exists($path)) {
                $missing[] = $name.' -> '.$file.'.svg';

                continue;
            }

            $inner = $this->innerMarkup(File::get($path));

            if ($inner === null) {
                $malformed[] = $name.' -> '.$file.'.svg';

                continue;
            }

            $drawings[$name] = $inner;
        }

        // Report every problem before giving up, so one run tells the whole
        // story instead of surfacing them one run at a time.
        if ($missing !== []) {
            foreach ($missing as $line) {
                $this->components->error('No such Lucide icon: '.$line);
            }
        }

        if ($malformed !== []) {
            foreach ($malformed as $line) {
                $this->components->error('Could not read the drawing inside: '.$line);
            }
        }

        if ($missing !== [] || $malformed !== []) {
            return self::FAILURE;
        }

        $target = base_path('resources/icons/lucide.php');
        $contents = $this->render($drawings);

        if ($this->option('check')) {
            $current = File::exists($target) ? File::get($target) : null;

            if ($current === $contents) {
                $this->components->info('Icons are up to date. '.count($drawings).' drawings.');

                return self::SUCCESS;
            }

            $this->components->error('The baked icon file is stale. Run: php artisan icons:sync');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($target));
        File::put($target, $contents);

        $this->components->info('Baked '.count($drawings).' Lucide drawings into resources/icons/lucide.php');

        return self::SUCCESS;
    }

    /**
     * Pull the shapes out of a Lucide file and drop the wrapper.
     *
     * Only the inner markup is kept, because the component supplies its own
     * svg element with the size, the colour and the accessibility attributes.
     * A file that cannot be read this way returns null rather than an empty
     * string, so a silent blank icon is impossible.
     */
    private function innerMarkup(string $svg): ?string
    {
        if (preg_match('/<svg\b[^>]*>(.*)<\/svg>/s', $svg, $matches) !== 1) {
            return null;
        }

        $inner = trim($matches[1]);

        if ($inner === '' || str_contains($inner, '<svg') || str_contains($inner, '</svg>')) {
            return null;
        }

        return $inner;
    }

    /**
     * Write the map as a PHP file a human can read in a diff.
     *
     * The shapes are collapsed onto one line each, because a path is one
     * attribute list and splitting it across lines turns a one line change
     * into a diff nobody can review.
     *
     * @param  array<string, string>  $drawings
     */
    private function render(array $drawings): string
    {
        $lines = [];

        foreach ($drawings as $name => $inner) {
            $collapsed = preg_replace('/\s*\n\s*/', ' ', $inner) ?? $inner;
            $lines[] = '    '.var_export($name, true).' => \''.$collapsed.'\',';
        }

        $version = $this->packageVersion();

        return <<<PHP
        <?php

        /*
        |--------------------------------------------------------------------------
        | Baked icon drawings
        |--------------------------------------------------------------------------
        |
        | Generated by `php artisan icons:sync`. Do not edit by hand.
        |
        | Each value is the inner markup of one Lucide drawing, stripped of its
        | own svg wrapper because the x-icon component supplies that. The keys
        | come from config/icons.php, which is where the choice of drawing is
        | made. Run the sync command again after changing either file.
        |
        | Lucide {$version} is ISC licensed, Copyright (c) Lucide Icons and
        | Contributors. See resources/icons/README.md.
        |
        */

        return [
        {$this->indent($lines)}
        ];

        PHP;
    }

    /** Read the installed version so the header records what was actually baked. */
    private function packageVersion(): string
    {
        $package = base_path('node_modules/lucide-static/package.json');

        if (! File::exists($package)) {
            return 'unknown';
        }

        $decoded = json_decode(File::get($package), true);

        return is_array($decoded) && isset($decoded['version'])
            ? 'v'.(string) $decoded['version']
            : 'unknown';
    }

    /**
     * @param  list<string>  $lines
     */
    private function indent(array $lines): string
    {
        return implode("\n", $lines);
    }
}
