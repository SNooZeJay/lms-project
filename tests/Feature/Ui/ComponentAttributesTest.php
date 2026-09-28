<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A component that discards the class a caller passes it.
 *
 * Blade hands every component a ComponentAttributeBag, and a component that
 * writes its class literally on the root element throws that bag away. Nothing
 * warns, nothing throws, and the view reads as though the spacing is handled:
 *
 *     <x-form-errors :errors="$errors" class="mt-6" />
 *
 * looks correct and renders an error summary flush against the field above it.
 * Twenty eight call sites did exactly that. The same fault dropped
 * `lg:col-span-2` on the dashboard charts, which is why all three dashboards had
 * a dead third column, and dropped `shrink-0` on a status badge.
 *
 * A layout bug of this kind is invisible in review, because the code says the
 * right thing. It is only visible in the rendered page, so it is pinned here
 * against the source, which is the one place it can be caught cheaply.
 */
class ComponentAttributesTest extends TestCase
{
    /**
     * Components that deliberately do not take a class from the caller.
     *
     * Only `skeleton` belongs here. It declares a prop literally called `class`
     * and applies it itself, so merging the attribute bag as well would put the
     * same utility on the element twice and give the caller two ways to say the
     * same thing.
     *
     * Each entry carries its reason, because an exemption with no explanation
     * is indistinguishable from an oversight, and the second test below proves
     * the exemption is still true rather than taken on trust.
     *
     * @return array<string, string>
     */
    private const DELIBERATELY_SEALED = [
        'skeleton.blade.php' => 'Declares a prop called class and applies it itself, so merging would apply the same utility twice.',
    ];

    public function test_every_component_merges_the_attributes_a_caller_passes(): void
    {
        $sealed = self::DELIBERATELY_SEALED;
        $unmerged = [];

        foreach ($this->components() as $name) {
            // A component that merges is done, whatever it is called.
            if (str_contains((string) File::get($this->path($name)), '$attributes')) {
                continue;
            }

            // A component that is sealed on purpose is also done, and the next
            // test checks that the seal is still justified.
            if (isset($sealed[$name])) {
                continue;
            }

            $unmerged[] = $name;
        }

        $this->assertSame(
            [],
            $unmerged,
            "These components discard a class handed to them, so every call site using one is a silent no-op:\n  "
                .implode("\n  ", $unmerged)
        );
    }

    /**
     * A sealed component is only allowed if nothing actually calls it with a
     * class, so the exemption cannot quietly become a hole.
     */
    public function test_a_sealed_component_is_not_called_with_a_class_anywhere(): void
    {
        $offenders = [];

        foreach (array_keys(self::DELIBERATELY_SEALED) as $file) {
            $component = str_replace('.blade.php', '', $file);

            // The tag a view writes, so component/badge.blade.php is x-badge and
            // a nested one such as home/course-grid is x-home.course-grid.
            $tag = str_contains($component, '/')
                ? 'x-'.str_replace('/', '.', $component)
                : 'x-'.$component;

            foreach ($this->views() as $view) {
                $source = (string) File::get($view);

                if (preg_match('/<'.preg_quote($tag, '/').'\b[^>]*\bclass=/s', $source) === 1) {
                    $offenders[] = "{$component} is called with a class in ".basename($view);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These components are recorded as taking no caller class, but a view passes one:\n  ".implode("\n  ", $offenders)
        );
    }

    /**
     * The specific faults, so a regression names the thing that broke rather
     * than only the rule.
     */
    public function test_the_error_summary_keeps_the_margin_its_caller_asked_for(): void
    {
        $source = (string) File::get($this->path('form-errors.blade.php'));

        $this->assertStringContainsString(
            '$attributes->merge',
            $source,
            'form-errors discards the class its caller passes, so every form error summary in the application lost the margin it was given.'
        );
    }

    public function test_the_dashboard_charts_keep_the_column_span_they_are_given(): void
    {
        foreach (['bar-chart.blade.php', 'activity-agenda.blade.php'] as $component) {
            $this->assertStringContainsString(
                '$attributes->merge',
                (string) File::get($this->path($component)),
                "{$component} discards the lg:col-span-2 its caller passes, which left a dead third column on the dashboard."
            );
        }
    }

    public function test_a_status_forwards_the_class_it_is_given_to_the_badge(): void
    {
        $this->assertStringContainsString(
            '$attributes',
            (string) File::get($this->path('status.blade.php')),
            'status drops the class a caller passes, so shrink-0 on a badge had no effect.'
        );
    }

    /**
     * A card's heading starts the same distance from its border as every other
     * card's heading, whichever component produced it.
     */
    public function test_every_card_component_insets_its_heading(): void
    {
        foreach (['bar-chart.blade.php', 'activity-agenda.blade.php'] as $component) {
            $source = (string) File::get($this->path($component));

            $this->assertStringContainsString(
                'card-header',
                $source,
                "{$component} puts its heading straight inside the card, so the title sits one pixel from the border while every other card insets it by twenty one."
            );

            $this->assertStringContainsString(
                'card-body',
                $source,
                "{$component} does not use card-body for its content."
            );
        }
    }

    /** @return list<string> */
    private function components(): array
    {
        $files = [];

        foreach (File::files(resource_path('views/components')) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getFilename();
            }
        }

        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function views(): array
    {
        $files = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function path(string $component): string
    {
        return resource_path('views/components/'.$component);
    }
}
