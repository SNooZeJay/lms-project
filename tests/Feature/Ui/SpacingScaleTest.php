<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * The layout system's spacing values come from the approved scale.
 *
 * `docs/design.md` section 6 gives the scale: 4, 8, 12, 16, 20, 24, 32, 40, 48,
 * 64. A scale earns its keep by letting a value be chosen by naming its position
 * rather than by measuring. 48 is two steps down; 56 is a bit more than 48; and
 * only one of those survives being written down in a second file.
 *
 * The layout classes used 56 and 80. Both were defensible on the page and neither
 * was on the scale, and the reasons they were chosen are the reasons they are
 * wrong. The desktop gap looked too wide at 64 and too tight at 48, so it became
 * 56. A closing band wanted more room than a middle one, so it became 80. A scale
 * that is adjusted to taste is not a scale, and the first value measured against
 * it is the one the next person copies.
 *
 * WHY THIS IS NOT IN `HomePageRhythmTest`
 *
 * It was, and every run of it failed on a database error rather than on the
 * assertion. The check reads two files and asserts nothing about the database, but
 * the class it lived in uses `RefreshDatabase`, so each run migrated the test
 * schema first. On this MySQL a `migrate:fresh` that runs twice in quick
 * succession deadlocks on its own `DROP TABLE` and leaves a half dropped schema,
 * and the next run then fails on a missing foreign key. The result was a test
 * about arithmetic in a stylesheet reporting a deadlock, which is the worst kind
 * of wrong: a real failure hiding behind an unrelated one.
 *
 * A check that needs no database does not belong in a class that migrates one.
 *
 * WHAT IS AND IS NOT CHECKED
 *
 * The layout system by name, not the whole stylesheet. A whole sheet check fails
 * on values that are legitimately not spacing, such as a one pixel hairline or a
 * negative offset, and a rule that cannot be satisfied gets deleted rather than
 * obeyed. The classes listed here are the ones this pass introduced, and they are
 * the ones whose values a reader picks from.
 */
class SpacingScaleTest extends TestCase
{
    /**
     * Every spacing value in the layout system is on the approved scale.
     *
     * The scale is `4, 8, 12, 16, 20, 24, 32, 40, 48, 64` and it is written down
     * in `docs/design.md` section 6, which is the source of truth for the visual
     * direction. A scale earns its keep by letting a value be chosen by naming its
     * position rather than by measuring: 48 is "two steps down", 56 is "a bit more
     * than 48", and only one of those survives being written in a second file.
     *
     * These rules did use 56 and 80. Both were reasonable on the page and neither
     * was on the scale, and the reason they were chosen is the reason they are
     * wrong: the desktop gap looked too wide at 64 and too tight at 48, so it was
     * set to 56, and a closing band wanted more room than a middle one, so it was
     * set to 80. A scale that gets adjusted to taste is not a scale, and the first
     * value measured against it is the one the next person copies.
     *
     * What is checked is the layout system by name rather than the whole
     * stylesheet. Every value on the whole sheet would fail on values that are
     * legitimately outside a spacing scale, such as a 1 pixel hairline or a
     * negative offset, and a rule that cannot be satisfied gets deleted rather than
     * obeyed. The classes listed here are the ones this pass introduced, and they
     * are the ones whose values a reader picks from.
     */
    public function test_the_layout_system_uses_only_values_from_the_approved_scale(): void
    {
        // Read from the design document rather than restated, so changing the
        // scale is a change to the document and the test follows it.
        $design = (string) file_get_contents(base_path('docs/design.md'));

        /*
         | Anchored on the section heading, because the document holds three
         | `text` blocks and the first of them is only the spacing scale by
         | coincidence of ordering. Taking whichever came first meant a text
         | block added to an earlier section would silently replace the scale,
         | and the test would then pass or fail against numbers the design
         | never approved.
         */
        $this->assertSame(
            1,
            preg_match('/^## 6\. .*?```text\s*\n(.*?)\n?```/ms', $design, $block),
            'The spacing scale could not be read out of docs/design.md section 6, so this test proved nothing.'
        );
        /*
         | The scale is written as one comma separated line, not one value per
         | line. Parsing it as lines was the first attempt and found nothing,
         | which is the useful kind of failure: a parser that returns an empty
         | list produces a test that passes for the wrong reason, so the list
         | being non empty is asserted rather than assumed.
         */
        $allowed = array_values(array_unique(array_filter(array_map(
            static fn (string $n): int => (int) $n,
            preg_split('/[\s,]+/', trim($block[1])) ?: []
        ))));

        $this->assertNotSame([], $allowed, 'The scale in docs/design.md is empty.');

        $css = (string) file_get_contents(resource_path('css/app.css'));

        $classes = [
            'shell', 'band', 'band-first', 'band-last', 'band-head', 'measure',
            'figures', 'panel-call', 'steps', 'step-marker', 'step-index',
        ];

        $offenders = [];
        $missing = [];

        foreach ($classes as $class) {
            if (preg_match('/\.'.preg_quote($class, '/').'\s*\{([^}]*)\}/', $css, $m) !== 1) {
                // Recorded rather than skipped. A named class with no rule here
                // means the coverage shrank, and a test that quietly stops
                // checking something is worse than one that fails.
                $missing[] = $class;

                continue;
            }

            /*
             | Only spacing utilities. Sizes, colours, line heights and positions
             | are not spacing and are not on the scale.
             |
             | The value stops at a semicolon rather than at any non space
             | character, because the last utility in a declaration carries one
             | and `\S+` swallowed it. `lg:py-14;` then failed the numeric match
             | below and was skipped, so the final value of every rule went
             | unchecked while the test still passed.
             */
            preg_match_all('/(?:^|\s)((?:sm:|md:|lg:|xl:|2xl:)?[pm][trblxy]?-[^;\s]+)/', $m[1], $tokens);

            foreach ($tokens[1] as $token) {
                // Strip any arbitrary value and any fraction, both of which are
                // not scale steps.
                $bare = preg_replace('/-\[.*$/', '', $token);
                $bare = preg_replace('/^\S+?:/', '', $bare);

                if (! preg_match('/^[pm][trblxy]?-(\d+(?:\.\d+)?)$/', $bare, $n)) {
                    continue;
                }

                /*
                 | The scale in `docs/design.md` is written in pixels, and Tailwind
                 | numbers its spacing in quarter rems, so one step is 0.25rem.
                 | A root font size of 16px makes that four pixels per step.
                 |
                 | Comparing the two directly is what the first version of this did,
                 | and it reported `py-10` as 3px, which is neither the rem value nor
                 | the pixel value. Every rule failed, including the ones that were
                 | corrected to be on the scale, so the arithmetic had to be right
                 | before the list meant anything.
                 */
                $px = (float) $n[1] * 4;

                if (! in_array((int) round($px), $allowed, true)) {
                    $offenders[] = sprintf('.%s uses %s, which is %dpx and not on the scale', $class, $token, round($px));
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These classes are named as covered but have no rule in the stylesheet, so nothing was '
            ."checked for them. Remove the name or restore the rule:\n  ".implode("\n  ", $missing)
        );

        $this->assertSame(
            [],
            $offenders,
            'These values are not on the spacing scale in docs/design.md section 6, so there is '
            ."nothing to pick the next one from:\n  ".implode("\n  ", $offenders)
        );
    }
}
