<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The brand has one shape: the mark with the product name beside it as live text.
 *
 * Two earlier versions are worth keeping out. A lockup image with the name baked
 * in had to be carried at 2.4 MB and its dark navy lettering was unreadable on the
 * dark theme. The previous mark was a different drawing entirely.
 *
 * These tests fail if either comes back, and if an asset a view names is missing
 * from disk, which is the failure that shows up as a broken image rather than an
 * error.
 */
class BrandAssetTest extends TestCase
{
    public function test_the_name_is_live_text_so_it_reads_in_both_themes(): void
    {
        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        // The mark is decorative, the name is text beside it.
        $this->assertStringContainsString('data-brand-mark', $body);
        $this->assertMatchesRegularExpression(
            '/data-brand-mark[^>]*>\s*<span[^>]*>\s*<span[^>]*>IT Learning Hub/',
            $body,
            'The product name should be live text beside the mark.'
        );
    }

    public function test_the_retired_lockup_and_old_mark_are_gone(): void
    {
        $body = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-brand-lockup', $body);
        $this->assertStringNotContainsString('lms-lockup', $body);
        $this->assertStringNotContainsString('lms-mark.png', $body);
    }

    public function test_every_brand_asset_a_view_names_exists_on_disk(): void
    {
        $referenced = [];

        // A recursive iterator, because glob does not walk nested directories
        // the same way on every platform.
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            preg_match_all('#images/brand/([a-z0-9-]+\.png)#', $contents, $matches);

            foreach ($matches[1] as $name) {
                $referenced[$name] = true;
            }
        }

        $this->assertNotEmpty($referenced, 'A view should name a brand asset.');

        foreach (array_keys($referenced) as $name) {
            $this->assertFileExists(
                public_path('images/brand/'.$name),
                "A view names images/brand/{$name} but it is not on disk."
            );
        }
    }

    public function test_no_brand_asset_is_carried_larger_than_it_is_drawn(): void
    {
        // The supplied mark is 919 KB at 800 pixels. Anything the interface draws
        // has to be a resampled derivative, never the source.
        $source = base_path('resources/it-lms-logo-only.png');

        $this->assertFileExists($source, 'The source mark should stay in resources/.');

        foreach (glob(public_path('images/brand/*.png')) ?: [] as $asset) {
            $this->assertLessThan(
                200_000,
                filesize($asset),
                basename($asset).' is larger than any brand asset needs to be.'
            );
        }
    }

    public function test_the_favicon_is_the_current_mark(): void
    {
        $this->assertFileExists(public_path('favicon.png'));

        // The favicon is generated from the 64 pixel mark, so the two must match
        // or the browser tab and the header show different drawings.
        $this->assertSame(
            filesize(public_path('favicon.png')),
            filesize(public_path('images/brand/lms-mark-64.png')),
            'The favicon should be the 64 pixel mark.'
        );
    }
}
