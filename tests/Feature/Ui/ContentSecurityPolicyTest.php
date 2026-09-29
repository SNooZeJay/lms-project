<?php

namespace Tests\Feature\Ui;

use App\Support\CourseCoverCatalog;
use Tests\TestCase;

/**
 * The content security policy has to permit what the pages actually do.
 *
 * This exists because of two faults that the whole suite passed.
 *
 * THE FIRST: A COVER WAS REFUSED
 *
 * `img-src` was `'self' data:`, which is right for everything else in the
 * application and wrong for course covers. A photograph chosen from the catalog is
 * served from Unsplash's own CDN, so the browser was asked to fetch it from an
 * origin the policy did not name, and refused it. Every course card on the site
 * showed a broken image.
 *
 * Nothing reported it. The page answered 200, and the image request answered 200
 * too, and the refusal happened in the browser after both. Every check made with
 * an HTTP client therefore passed while the page showed nothing at all, which is
 * the specific way this class of fault hides: the thing being tested answers
 * correctly, and what the reader sees does not.
 *
 * The lesson is that an HTTP client cannot verify a policy. Only the policy's own
 * terms can, so that is what is asserted here.
 *
 * THE SECOND: AN INLINE HANDLER THAT COULD NEVER RUN
 *
 * The cover image carried `onerror="…"` to hide itself when it failed to load.
 * `script-src` is `'self'` plus a per request nonce, with no `unsafe-inline`,
 * which is the correct policy and which forbids inline handlers outright. The
 * browser discarded the attribute, so the fallback was absent on exactly the pages
 * that needed it. The handler is in the bundle now, and this test keeps any others
 * from being written into a template.
 */
class ContentSecurityPolicyTest extends TestCase
{
    /**
     * The `img-src` sources, read from a real response.
     *
     * @return list<string>
     */
    private function imgSrcSources(): array
    {
        $response = $this->get(route('home'))->assertOk();

        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertNotSame('', $policy, 'The response carries no content security policy at all.');

        foreach (explode(';', $policy) as $directive) {
            if (! str_starts_with(trim($directive), 'img-src')) {
                continue;
            }

            $sources = array_map('trim', explode(' ', trim($directive)));

            return array_values(array_filter(array_slice($sources, 1)));
        }

        $this->fail('The policy has no img-src directive, so no image can be loaded at all.');
    }

    /**
     * Every origin a cover photograph is served from must be permitted.
     *
     * The origins are read out of the class that builds the URLs rather than
     * written here as a list. A list written in the test would agree with the
     * policy by being edited alongside it, and would keep agreeing after the
     * catalog started serving photographs from somewhere new. Reading the real
     * URLs means a new origin fails this test instead of failing to appear.
     */
    public function test_the_policy_permits_every_origin_a_cover_photograph_is_served_from(): void
    {
        $sources = $this->imgSrcSources();

        $this->assertContains("'self'", $sources, 'Images served by this application must stay permitted.');

        $origins = [];

        foreach (CourseCoverCatalog::identifiers() as $identifier) {
            $host = parse_url(CourseCoverCatalog::urlFor($identifier, 640, 360), PHP_URL_HOST);

            $this->assertIsString($host, "The cover catalog produced a URL with no host for {$identifier}.");

            $origins[$host] = true;
        }

        $this->assertNotEmpty($origins, 'The cover catalog offered no photographs to check.');

        foreach (array_keys($origins) as $host) {
            $this->assertContains(
                'https://'.$host,
                $sources,
                "A course cover is served from {$host}, and img-src does not permit it, so every "
                .'cover on the site will be refused by the browser.'
            );
        }
    }

    /**
     * Every other image this application serves is its own, so `'self'` covers it.
     *
     * Written down so that a change which starts serving images from a second
     * origin has to update this and the directive above together.
     */
    public function test_no_third_party_image_origin_is_permitted_beyond_the_cover_host(): void
    {
        $sources = $this->imgSrcSources();

        $external = array_values(array_filter(
            $sources,
            fn (string $source): bool => $source !== "'self'" && $source !== 'data:'
        ));

        $coverHost = parse_url(
            CourseCoverCatalog::urlFor(CourseCoverCatalog::identifiers()[0], 640, 360),
            PHP_URL_HOST
        );

        $this->assertSame(
            ['https://'.$coverHost],
            $external,
            'img-src permits an origin this application does not serve images from. A policy is a '
            .'list of what is needed, and an entry nobody can name the reason for is a hole.'
        );
    }

    /**
     * The policy must not be opened up to make a page work.
     */
    public function test_the_policy_grants_no_wildcard_and_no_inline(): void
    {
        $policy = (string) $this->get(route('home'))->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString("'unsafe-inline'", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringNotContainsString('*', $policy);
    }

    /**
     * No template may carry an inline event handler.
     *
     * The policy forbids them, so one written into a template is dead code that
     * looks alive. That is worse than having no handler at all: the behaviour it
     * was written to provide is absent, and the markup reads as though it is
     * present.
     *
     * `onerror` on a course cover was exactly this, for exactly this reason.
     */
    public function test_no_template_carries_an_inline_event_handler(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'))
        );

        $offenders = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            // Matched inside a tag rather than anywhere in the file, so a word
            // such as `only` in a sentence does not read as `on` followed by `ly`.
            if (preg_match('/<[a-z][^>]*\son[a-z]+\s*=/i', $contents) === 1) {
                preg_match_all('/<[a-z][^>]*\son([a-z]+)\s*=/i', $contents, $matches);

                $offenders[$file->getFilename()] = array_values(array_unique($matches[1]));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These templates carry an inline event handler, which this application's content "
            ."security policy refuses to run:\n  ".implode(
                "\n  ",
                array_map(
                    fn (string $file, array $handlers): string => $file.' ('.implode(', ', $handlers).')',
                    array_keys($offenders),
                    $offenders,
                )
            )
        );
    }

    /**
     * The cover fallback has to be real JavaScript, and it has to be captured.
     */
    public function test_the_cover_fallback_runs_from_the_bundle_and_not_from_an_attribute(): void
    {
        $scripts = glob(public_path('build/assets/app-*.js')) ?: [];

        $this->assertNotSame([], $scripts, 'The bundled script was not found. Run the asset build first.');

        $bundle = implode("\n", array_map('file_get_contents', $scripts));

        // The shipped artifact, asked the question a reader's browser would ask:
        // does the code that actually loads carry the hook?
        $this->assertStringContainsString('data-cover-image', $bundle);

        // The authored source, asked about the capture flag. The minifier rewrites
        // the quotes and collapses `true` to `!0`, so pinning either of those in
        // the bundle would be a test of the minifier rather than of the code.
        $source = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertMatchesRegularExpression(
            '/addEventListener\(\s*[\'"]error[\'"]/',
            $source,
            'The cover fallback should be one delegated listener rather than a handler per image.'
        );

        // Captured, because an error event on an element does not bubble and a
        // bubble phase listener on the document never hears one. Without the
        // capture flag the handler is registered, present in the bundle, and
        // never called, which looks exactly like a fallback that is not needed.
        $this->assertMatchesRegularExpression(
            '/addEventListener\(\s*[\'"]error[\'"].*?true,?\s*\);/s',
            $source,
            'The cover fallback must be registered in the capture phase, or it never receives the '
            .'error event it exists to handle.'
        );
    }
}
