<?php

/**
 * Removes a view's page wrapper, leaving what is inside it.
 *
 * The wrapper is the first element inside `@section('content')` and its closer is
 * the last one. Both are found by position rather than by matching a known
 * string, because the closer cannot be matched: the tag immediately above it
 * belongs to a Blade component such as `<x-page-header>` or `<x-empty-state>`,
 * which closes itself, so "the last `</div>` in the file" is the innermost card
 * and not the frame.
 *
 * That was tried, and it removed a closer that had a real opener above it. The
 * view kept its own markup, so nothing looked broken in the diff, and the browser
 * resolved the unclosed frame by swallowing everything after it: one file came
 * out with `@endsection` as its first line.
 *
 * So the depth is counted instead. Only `div` tags are counted, because a `div`
 * is the only thing that can enclose the frame's own children, and a component's
 * interior is balanced by the component's own tag. The opening tag is at depth
 * one; the closing tag that brings the count back to zero is the frame's.
 *
 * Anything that does not balance is returned untouched, with the reason, because a
 * view whose frame cannot be identified is a view for a person to look at rather
 * than for a script to rewrite.
 *
 * @return array{0: string, 1: string|null} the new source, and null when it is unchanged
 */
function removeWrapper(string $source, string $opener): array
{
    $start = strpos($source, $opener);

    if ($start === false) {
        return [$source, null];
    }

    // The opener occupies its own line, so the body begins after that line ends.
    $lineEnd = strpos($source, "\n", $start);

    if ($lineEnd === false) {
        return [$source, null];
    }

    $before = substr($source, 0, $start);
    $after = substr($source, $lineEnd + 1);

    $sectionEnd = strpos($after, '@endsection');
    $body = $sectionEnd === false ? $after : substr($after, 0, $sectionEnd);
    $tail = $sectionEnd === false ? '' : substr($after, $sectionEnd);

    $depth = 1;
    $closerAt = null;
    $offset = 0;

    while ($offset < strlen($body)) {
        $nextOpen = strpos($body, '<div', $offset);
        $nextClose = strpos($body, '</div>', $offset);

        if ($nextClose === false) {
            return [$source, null];
        }

        if ($nextOpen !== false && $nextOpen < $nextClose) {
            $depth++;
            $offset = $nextOpen + 4;

            continue;
        }

        $depth--;

        if ($depth === 0) {
            $closerAt = $nextClose;
            break;
        }

        $offset = $nextClose + 6;
    }

    if ($closerAt === null) {
        return [$source, null];
    }

    // Take the closer's whole line, indentation included, so the file does not
    // keep a line of trailing spaces where it used to be.
    $lineStart = strrpos(substr($body, 0, $closerAt), "\n");
    $cut = $lineStart === false ? 0 : $lineStart + 1;

    return [$before.substr($body, 0, $cut).$tail, 'moved'];
}
