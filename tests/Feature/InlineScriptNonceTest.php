<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every inline script in every template carries the request nonce.
 *
 * WHY THIS EXISTS
 *
 * The cover preview script in `course-cover-picker.blade.php` was pushed into the
 * page without a nonce. The content security policy did exactly what it was built
 * to do and blocked it. The consequence was invisible: the page rendered, the form
 * worked, the upload worked, and the preview simply never appeared. Nothing threw,
 * nothing looked broken, and no test failed.
 *
 * That is the worst class of defect there is. It is not that a feature errors, it
 * is that a feature is absent and the interface gives no sign of it — which is
 * also exactly how a capability gets claimed in a presentation and then turns out
 * not to exist when somebody tries it.
 *
 * WHY A STATIC CHECK AND NOT A RUNTIME ONE
 *
 * The obvious test renders every page and counts blocked scripts. That needs a
 * signed-in session per role, needs every route to be reachable, and produces a
 * different answer depending on which pages happen to be in the suite. This reads
 * the templates instead: it is complete by construction, it is fast, and it fails
 * on the commit that introduced the problem rather than on the day somebody
 * noticed the feature was quiet.
 *
 * The two are complementary and neither replaces the other. This cannot see a
 * nonce that is emitted empty, and a runtime check is what catches that. The
 * browser inventory in the audit is the runtime half.
 */
class InlineScriptNonceTest extends TestCase
{
    /**
     * No template may emit an inline script without the nonce.
     */
    public function test_every_inline_script_carries_the_nonce(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            // Matches an opening <script> tag and captures its attributes.
            preg_match_all('/<script\b([^>]*)>/i', $file->getContents(), $matches);

            foreach ($matches[1] as $attributes) {
                /*
                 | An external script needs no nonce.
                 |
                 | `src` makes the browser fetch a file the server already controls
                 | and already pointed at with a build hash, so a nonce on it would
                 | be decoration. A nonce is only ever needed for a script whose
                 | body is in the page, which is what `src` rules out.
                 */
                if (preg_match('/\bsrc\s*=/i', $attributes)) {
                    continue;
                }

                if (! preg_match('/\bnonce\s*=/i', $attributes)) {
                    $offenders[] = $this->relative($file->getPathname())
                        .' : <script'.trim($attributes).'>';
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These inline scripts have no nonce, so the content security policy blocks them and the code inside them never runs: '
                .implode(' | ', $offenders),
        );
    }

    /**
     * And the nonce is never written literally.
     *
     * A hard-coded nonce looks identical to a working one in a diff and is worth
     * nothing: a static string is not a per-request secret, so anybody reading the
     * repository could add a script to any page. The only correct source is the
     * request's own nonce, published by the header middleware.
     */
    public function test_no_nonce_is_hard_coded(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            // A Blade expression is fine. A literal string of base64 is not.
            if (preg_match('/nonce\s*=\s*["\'](?!\{\{)[A-Za-z0-9+\/=]{8,}["\']/i', $file->getContents(), $matches)) {
                $offenders[] = $this->relative($file->getPathname()).' : '.$matches[0];
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'A nonce written into a template is a constant, not a secret, and it would let anybody who reads the repository add a script to any page: '
                .implode(' | ', $offenders),
        );
    }

    /**
     * The scripts that do carry a nonce all read it the same way.
     *
     * Two spellings of the same value are two chances to publish an empty one, and
     * an empty nonce fails closed and silently, which is the failure this file
     * exists to prevent.
     */
    public function test_every_nonce_is_read_the_same_way(): void
    {
        $spellings = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            // The quote after the equals is optional, because a template may write
            // nonce="..." or leave the value bare, and both are the same element.
            preg_match_all('/nonce\s*=\s*["\']?\s*(\{\{[^}]*\}\})/i', $file->getContents(), $matches);

            foreach ($matches[1] as $expression) {
                $spellings[$expression][] = $this->relative($file->getPathname());
            }
        }

        $this->assertNotEmpty($spellings, 'No template reads a nonce at all, which means nothing is nonced.');

        $this->assertCount(
            1,
            $spellings,
            'Every nonce must be read the same way, and these spellings are in use: '
                .implode(' vs ', array_keys($spellings)).' — '
                .json_encode(array_map('count', $spellings)),
        );
    }

    /**
     * Paths relative to the project, so a failure names a file and not a machine.
     */
    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
