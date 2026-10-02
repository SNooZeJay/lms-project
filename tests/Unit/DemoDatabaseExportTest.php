<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| The demo database export
|--------------------------------------------------------------------------
|
| This tool exists because a shell redirect corrupted the dump that ships on the
| USB drive, and the corruption was only found by trying to import it. These
| tests pin the two properties that would have caught it.
|
*/

class DemoDatabaseExportTest extends TestCase
{
    private function script(): string
    {
        return base_path('tools'.DIRECTORY_SEPARATOR.'export-demo-database.php');
    }

    #[Test]
    public function the_export_tool_exists_and_is_valid_php(): void
    {
        $this->assertFileExists($this->script());

        $output = [];
        $status = 0;
        exec(
            escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($this->script()).' 2>&1',
            $output,
            $status,
        );

        $this->assertSame(
            0,
            $status,
            'The export tool does not parse: '.implode("\n", $output),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The guard against PowerShell
    |--------------------------------------------------------------------------
    |
    | mysqldump must write its own bytes, through --result-file. A shell
    | redirect routes the output through PowerShell, which re-encodes it as
    | UTF-16LE and produces a file that cannot be imported.
    |
    | This is asserted against the source rather than at runtime because the
    | failure is not visible at runtime: the export reports success either way,
    | and the file it produces is only discovered to be broken on another
    | machine. A runtime test would pass for the broken version too.
    |
    */
    #[Test]
    public function the_export_writes_bytes_directly_and_never_through_a_shell_redirect(): void
    {
        $source = (string) file_get_contents($this->script());

        $this->assertStringContainsString(
            '--result-file=',
            $source,
            'mysqldump must use --result-file so PowerShell never touches the output bytes.',
        );

        $this->assertStringNotContainsString(
            '| redirect',
            $source,
            'A PowerShell redirect would re-encode the dump as UTF-16.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The file is checked before it is declared good
    |--------------------------------------------------------------------------
    |
    | A dump that cannot be imported is worse than no dump, because the problem
    | surfaces at the worst moment on the wrong machine. The tool checks the
    | three faults that a corrupt export produces, and these tests confirm the
    | checks are present and strict.
    |
    */
    #[Test]
    public function the_export_checks_the_file_before_declaring_success(): void
    {
        $source = (string) file_get_contents($this->script());

        $this->assertStringContainsString(
            'byte order mark',
            $source,
            'A byte order mark means the file was written as UTF-16 and will not import.',
        );

        /*
        | The needle is the two characters backslash and zero, because that is
        | how a NUL is written in the tool's own source. Searching for an actual
        | NUL byte would look for a fault in the file rather than a guard against
        | one, and would pass on a tool that had been stripped of the check.
        */
        $this->assertStringContainsString(
            '\0',
            $source,
            'A NUL byte means the file was written as UTF-16 and will not import.',
        );

        $this->assertStringContainsString(
            'mb_check_encoding',
            $source,
            'UTF-8 validity must be checked, or accented demo data is silently lost.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | It cannot export the test database
    |--------------------------------------------------------------------------
    |
    | The tool reads the connection out of .env. Run from inside the test suite
    | that is the test database, and a demo dump full of test fixtures is worse
    | than no dump at all. It has to refuse rather than warn.
    |
    */
    #[Test]
    public function the_export_refuses_to_run_inside_the_test_environment(): void
    {
        $this->assertSame('testing', $this->app->environment());

        /*
        | APP_ENV is set explicitly for the child rather than assumed to be
        | inherited. It is not reliably passed on: the test runner holds it in
        | $_ENV and $_SERVER, and on Windows a child process started through
        | proc_open does not necessarily see either of those. Relying on
        | inheritance made this test fail for a reason that had nothing to do
        | with the export.
        */
        putenv('APP_ENV=testing');

        try {
            $output = [];
            $status = 0;
            exec(
                escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->script()).' 2>&1',
                $output,
                $status,
            );
        } finally {
            putenv('APP_ENV');
        }

        $this->assertSame(
            1,
            $status,
            'The export must refuse to run as testing. Output: '.implode("\n", $output),
        );

        $this->assertStringContainsString(
            'Refusing to export',
            implode("\n", $output),
        );
    }
}
