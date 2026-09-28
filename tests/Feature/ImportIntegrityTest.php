<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * No file may use a short class name it has not imported.
 *
 * PHP does not complain about a missing `use`. It resolves the short name
 * against whatever namespace the file happens to be in, and only fails later, at
 * the point of use, as a BindingResolutionException from inside a view render.
 * That makes a missing import look like a broken feature rather than a broken
 * file, which is how two of them survived a green test suite here.
 *
 * So this runs the scanner rather than duplicating it, and fails on any name it
 * cannot account for. New false positives belong in the known list inside the
 * scanner, with a note saying why, rather than being quietly tolerated.
 */
class ImportIntegrityTest extends TestCase
{
    public function test_every_short_class_name_is_imported_or_defined_in_its_file(): void
    {
        $scanner = dirname(__DIR__, 2).'/tools/check-imports.php';

        $this->assertFileExists($scanner);

        $output = [];
        $exit = 0;

        exec(escapeshellcmd(PHP_BINARY).' '.escapeshellarg($scanner).' 2>&1', $output, $exit);

        $report = trim(implode("\n", $output));

        $this->assertSame(0, $exit, "The import scanner itself failed:\n".$report);

        $this->assertStringContainsString(
            'every short class name is imported or defined in its file',
            $report,
            "A file uses a short class name without importing it:\n".$report
        );
    }
}
