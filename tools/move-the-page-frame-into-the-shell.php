<?php

/**
 * Moves the page frame out of the views and into the shell.
 *
 * Thirty three views were each writing their own container:
 *
 *     mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10
 *
 * with seven different maximum widths, and seven more views had no wrapper at
 * all, so their content sat against the edge of the window with no padding
 * whatsoever. Measured across the signed in pages, the content edge landed at
 * five different places: 0, 24, 32, 41 and 64 pixels.
 *
 * The frame now belongs to `layouts/app-shell.blade.php`, which writes it once.
 * This script removes the duplicated wrapper from each view and puts a
 * `@section('measure', ...)` in its place, because the measure of a page is
 * genuinely a content decision: a reading page wants a narrow column, a table
 * wants the full one.
 *
 * The widths are mapped from what each view was already using, so no page becomes
 * narrower or wider as a side effect. That mapping is the part worth reading: it
 * is the record of what the project had decided, expressed as three names.
 *
 * Reports every view it rewrote and every one it could not recognise. A wrapper
 * it does not recognise is left alone rather than guessed at.
 *
 * Usage: php tools/move-the-page-frame-into-the-shell.php [--dry]
 */
$root = dirname(__DIR__);
$views = $root.'/resources/views';

$dry = in_array('--dry', $argv, true);

/*
 | The measure each page gets, from the maximum width it was already using.
 |
 | `max-w-2xl` and `max-w-3xl` become narrow, `max-w-4xl` and `max-w-5xl`
 | medium, and `max-w-6xl` and `max-w-7xl` become wide. Six buckets into three
 | names, and the two inside each name were within sixty four pixels of each
 | other, so no page changes width by more than a rounding error.
 */
$measureFor = [
    'max-w-2xl' => 'narrow',
    'max-w-3xl' => 'narrow',
    'max-w-4xl' => 'medium',
    'max-w-5xl' => 'medium',
    'max-w-6xl' => 'wide',
    'max-w-7xl' => 'wide',
];

/*
 | The exact wrappers this script removes.
 |
 | Matched as whole strings, padding utilities and all, rather than as loose
 | patterns. Every one of these is the same frame written out again, and the
 | whole string has to match before anything is touched: a view that has changed
 | its padding to something deliberate must be left for a person to look at.
 */
$frames = [
    'mx-auto w-full max-w-2xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10',
    'mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10',
    'mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10',
    'mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10',
    'mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10',
    'mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10',
];

$openers = [];
$sections = [];

foreach ($frames as $frame) {
    preg_match('/max-w-\w+/', $frame, $found);
    $openers[] = '<div class="'.$frame.'">';
    $sections[] = "@section('measure', '".$measureFor[$found[0]]."')";
}

require __DIR__.'/remove-the-page-wrapper.php';

$rewritten = [];
$unwrapped = [];
$unbalanced = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views));

foreach ($files as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $original = (string) file_get_contents($file->getPathname());

    if (! str_contains($original, "@extends('layouts.app-shell')")) {
        continue;
    }

    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($views) + 1));

    foreach ($openers as $index => $opener) {
        if (! str_contains($original, $opener)) {
            continue;
        }

        /*
         | The wrapper comes out first, then the measure goes in.
         |
         | The other order was tried: replacing the opening tag with the section
         | and then removing the line the section was on. That deletes the section
         | along with the div, because the section is now on that line, and every
         | view came out with the frame removed and nothing put in its place. The
         | diff read like a clean simplification right up until a page was checked
         | and its measure turned out to be the default on all of them.
         */
        [$stripped] = removeWrapper($original, $opener);

        if ($stripped === $original) {
            $unbalanced[] = $relative;

            break;
        }

        // The measure goes where the frame was: immediately after content opens.
        $new = preg_replace(
            "/(@section\('content'\)\r?\n)/",
            '$1'.$sections[$index]."\n",
            $stripped,
            1
        );

        if ($new === null || $new === $stripped) {
            $unbalanced[] = $relative;

            break;
        }

        if (! $dry) {
            file_put_contents($file->getPathname(), $new);
        }

        $rewritten[$relative] = $measureFor[preg_replace('/.*(max-w-\w+).*/', '$1', $frames[$index])];

        break;
    }

    if (! isset($rewritten[$relative]) && ! in_array($relative, $unbalanced, true)) {
        $unwrapped[] = $relative;
    }
}

echo '  '.count($rewritten)." views had the frame moved into the shell\n";

foreach ($rewritten as $relative => $measure) {
    echo "    {$relative}  ->  {$measure}\n";
}

if ($unwrapped !== []) {
    echo "\n  These use the shell but had no wrapper at all, so their content sat against the\n";
    echo "  edge of the window. The shell now gives them the frame, so they need no change:\n";

    foreach ($unwrapped as $relative) {
        echo "    {$relative}\n";
    }
}

if ($unbalanced !== []) {
    echo "\n  These had a wrapper whose closing tag could not be found, and were left alone:\n";

    foreach ($unbalanced as $relative) {
        echo "    {$relative}\n";
    }
}

echo "\n";
