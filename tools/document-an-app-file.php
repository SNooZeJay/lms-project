<?php

/*
 | Adds a newly created file under app/ to the folder tree in the documentation.
 |
 | WHY THIS EXISTS
 |
 | Phase15\ProductionReadinessTest asserts that docs/folder-structure.md names every
 | file under app/, and that it names no file that does not exist. Adding a class is
 | therefore not finished until the tree has been told about it, and a tree drawn
 | with box characters is not something to edit by typing new ones through a shell.
 | The characters get mangled, the drawing stops lining up, and the result looks
 | like a documentation problem rather than a broken script.
 |
 | So every line this writes is built by taking the drawing from a line that is
 | already there and putting a name on the end. Whatever this file does to the
 | bytes, the alignment survives.
 |
 | WHERE THE ENTRY GOES
 |
 | The tree names a block by its own directory, not by the path down to it, so
 | Http/Responses/VerifyEmailResponse.php belongs under the existing "Responses/"
 | block. A block that is already there gets the new file appended to it, carrying
 | the drawing of its last child. A block that is not there is created as a sibling
 | of whatever follows its parent.
 |
 | Appending rather than sorting is deliberate. Deciding where a name belongs
 | alphabetically means working out which lines are children, and getting that
 | wrong placed the entry at the top of its block, under the block's own name,
 | which is a worse outcome than being slightly out of order. The blocks are in
 | alphabetical order, so the end is where a later name belongs anyway.
 |
 | IT IS SAFE TO RUN TWICE
 |
 | The first thing it does is check whether the file is already named. A
 | documentation file that grows a duplicate entry every time it is run is worse
 | than one that is out of date, and a tool that has to be trusted to be run once
 | is a tool that will be run twice.
 |
 | Usage: php tools/document-an-app-file.php <path under app/>
 |   php tools/document-an-app-file.php Http/Responses/VerifyEmailResponse.php
 */

$root = dirname(__DIR__);
$doc = $root.'/docs/folder-structure.md';

$wanted = str_replace('\\', '/', trim((string) ($argv[1] ?? ''), '/'));

if ($wanted === '') {
    echo PHP_EOL.'  nothing to do: no path was given.'.PHP_EOL.PHP_EOL;
    echo '  Usage: php tools/document-an-app-file.php <path under app/>'.PHP_EOL.PHP_EOL;

    exit(1);
}

$absolute = $root.'/app/'.$wanted;

if (! is_file($absolute)) {
    echo PHP_EOL.'  there is no file at app/'.$wanted.', so nothing was documented.'.PHP_EOL;
    echo '  A tree that names a file which does not exist is the other half of that'.PHP_EOL;
    echo '  same failure, and this tool will not create it.'.PHP_EOL.PHP_EOL;

    exit(1);
}

$file = basename($wanted);

$segments = array_values(array_filter(
    explode('/', dirname($wanted)),
    static fn (string $segment): bool => $segment !== '.' && $segment !== '',
));
$folder = $segments === [] ? null : (string) end($segments);
$parent = count($segments) > 1 ? $segments[count($segments) - 2] : null;

$contents = (string) file_get_contents($doc);

if (str_contains($contents, $file)) {
    echo PHP_EOL.'  '.$file.' is already named in the tree, so nothing was changed.'.PHP_EOL.PHP_EOL;

    exit(0);
}

if ($folder === null) {
    echo PHP_EOL.'  the path has no directory, so there is no block to add it to.'.PHP_EOL.PHP_EOL;

    exit(1);
}

$lines = explode("\n", $contents);

/**
 * The drawing in front of a name, with the name taken off the end.
 *
 * Found by taking everything up to the last space, because no entry in the tree
 * has a space in its name. The first version of this looked for a slash, since
 * directories end in one, and that returned the whole line including the
 * directory name, so the drawing measured for a block included the block's own
 * name and every depth comparison after it was comparing the wrong thing.
 */
$prefixOf = static function (string $line): string {
    $trimmed = rtrim($line);
    $lastSpace = strrpos($trimmed, ' ');

    return $lastSpace === false ? '' : substr($trimmed, 0, $lastSpace + 1);
};

/** The visible name at the end of a drawn line, with the drawing removed. */
$labelOf = static fn (string $line): string => trim((string) preg_replace('/[^\x20-\x7E]/', '', $line));

/** How many drawing characters precede the first name character. */
$depthOf = static fn (string $line): int => strlen($prefixOf($line));

$lineIndexOfBlock = static function (array $lines, string $name) use ($labelOf): ?int {
    foreach ($lines as $index => $line) {
        if ($labelOf($line) === $name.'/') {
            return $index;
        }
    }

    return null;
};

$blockStart = $lineIndexOfBlock($lines, $folder);

if ($blockStart !== null) {
    // Append to the end of an existing block, carrying the last child's drawing.
    $blockDepth = $depthOf($lines[$blockStart]);
    $insertAt = count($lines);
    $drawing = $prefixOf($lines[$blockStart]);

    for ($i = $blockStart + 1; $i < count($lines); $i++) {
        if (trim($lines[$i]) === '') {
            continue;
        }

        if ($depthOf($lines[$i]) <= $blockDepth) {
            $insertAt = $i;

            break;
        }

        $drawing = $prefixOf($lines[$i]);
    }

    array_splice($lines, $insertAt, 0, [$drawing.$file]);
    $what = 'added to the existing '.$folder.'/ block';
} else {
    // Create a block as a sibling of whatever follows its parent.
    if ($parent === null) {
        echo PHP_EOL.'  '.$folder.'/ is not in the tree and the path names no parent, so'.PHP_EOL;
        echo '  there is nowhere to put it. Add the line by hand.'.PHP_EOL.PHP_EOL;

        exit(1);
    }

    $parentStart = $lineIndexOfBlock($lines, $parent);

    if ($parentStart === null) {
        echo PHP_EOL.'  neither '.$folder.'/ nor '.$parent.'/ is in the tree, so there is'.PHP_EOL;
        echo '  nowhere to put this. Add the line by hand.'.PHP_EOL.PHP_EOL;

        exit(1);
    }

    $parentDepth = $depthOf($lines[$parentStart]);
    $placed = false;

    for ($i = $parentStart + 1; $i < count($lines); $i++) {
        if (trim($lines[$i]) === '') {
            continue;
        }

        if ($depthOf($lines[$i]) <= $parentDepth) {
            $siblingDrawing = $prefixOf($lines[$i]);
            $childDrawing = $prefixOf($lines[$i + 1] ?? $lines[$i]);

            array_splice($lines, $i, 0, [$siblingDrawing.$folder.'/', $childDrawing.$file]);
            $what = 'a new '.$folder.'/ block was created with the file in it';
            $placed = true;

            break;
        }
    }

    if (! $placed) {
        echo PHP_EOL.'  the end of the '.$parent.'/ block was not found, so nothing was'.PHP_EOL;
        echo '  changed. Add the line by hand.'.PHP_EOL.PHP_EOL;

        exit(1);
    }
}

file_put_contents($doc, implode("\n", $lines));

echo PHP_EOL;
echo '  '.$what.': '.$file.PHP_EOL;
echo '  run the readiness test to confirm the tree and the filesystem agree again.'.PHP_EOL;
echo PHP_EOL;
