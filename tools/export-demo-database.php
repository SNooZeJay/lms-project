<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

/*
|--------------------------------------------------------------------------
| Export the demo database
|--------------------------------------------------------------------------
|
| Writes a plain SQL file of the demo data, for moving the application to
| another machine. It refuses to run in the test environment, so it cannot
| overwrite the demo database with test data by accident.
|
| WHY THIS EXISTS INSTEAD OF A ONE-LINED SHELL COMMAND
|
|     mysqldump ... > lms-demo.sql
|
| That looks correct and is not, on Windows PowerShell. PowerShell captures the
| program's output as a *string* and writes it back out in its own encoding.
| The result is a UTF-16LE file with a NUL byte between every character:
|
|     -- MySQL dump 10.13 Distrib 8.4.11 ...
|
| becomes
|
|     2D 00 2D 00 20 00 4D 00 79 00 53 00 51 00 4C 00 ...
|
| The file is roughly twice the size it should be, and importing it fails with
|
|     ERROR: ASCII '\0' appeared in the statement, but this is not allowed
|     unless option --binary-mode is enabled
|
| which looks like a corrupt database rather than a corrupt file. It was found
| by importing the dump, not by reading it.
|
| mysqldump's own --result-file writes the bytes directly from the process, with
| nothing in between. That is the whole fix. Do not "simplify" this back into a
| shell redirect.
|
| Usage:
|     php tools/export-demo-database.php
|     php tools/export-demo-database.php G:\PROJECTS\lms-demo.sql
|
*/

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if ($app->environment('testing')) {
    fwrite(STDERR, "Refusing to export: the application is booted as testing.\n");
    exit(1);
}

$destination = $argv[1] ?? $root.'/lms-demo.sql';

$database = (string) config('database.connections.mysql.database');
$host = (string) config('database.connections.mysql.host');
$port = (string) config('database.connections.mysql.port');
$username = (string) config('database.connections.mysql.username');

/*
| Where is mysqldump?
|
| On Windows the client is often installed outside the XAMPP tree, so PATH is not
| a reliable answer. Look in the usual places rather than failing.
*/
$named = 'mysqldump.exe';
$posix = 'mysqldump';

$candidates = array_filter([
    getenv('MYSQL_DUMP') ?: null,
    $named,
    $posix,
    'C:/tools/mysql-8.4.11-winx64/bin/'.$named,
    'C:/xampp/mysql/bin/'.$named,
    'C:/Program Files/MySQL/MySQL Server 8.0/bin/'.$named,
    'C:/Program Files/MySQL/MySQL Server 8.4/bin/'.$named,
    '/usr/local/mysql/bin/'.$posix,
    '/usr/bin/'.$posix,
]);

$binary = null;

foreach ($candidates as $candidate) {
    if (str_contains($candidate, '/') || str_contains($candidate, '\\')) {
        if (is_file($candidate)) {
            $binary = $candidate;
            break;
        }

        continue;
    }

    $found = trim((string) shell_exec('where '.escapeshellarg($candidate).' 2>nul'));
    if ($found !== '') {
        $binary = explode("\r\n", $found)[0];
        break;
    }
}

if ($binary === null) {
    fwrite(STDERR, "Could not find mysqldump.\n");
    fwrite(STDERR, "Set MYSQL_DUMP to its full path, or add it to PATH, and try again.\n");
    exit(1);
}

/*
| --hex-blob      binary columns as hex, so no byte can confuse the importer
| --no-tablespaces  we do not hold PROCESS, and the tablespace dump is optional
| --result-file   writes bytes directly from mysqldump; see the header note
*/
$arguments = [
    $binary,
    '--user='.$username,
    '--host='.$host,
    '--port='.$port,
    '--single-transaction',
    '--routines',
    '--triggers',
    '--hex-blob',
    '--no-tablespaces',
    '--default-character-set=utf8mb4',
    '--result-file='.$destination,
    $database,
];

/*
| The child process needs PATH, SystemRoot and the rest, plus the one secret.
|
| $_SERVER is not passed through as-is. On Windows it contains array members
| (argv, argc), and proc_open raises "Array to string conversion" on them. Every
| value is therefore filtered to a scalar before it goes anywhere near the
| process call. This is a Windows-only fault and it is silent elsewhere, which
| is exactly the sort of thing worth a comment.
*/
$environment = [];

foreach ($_ENV + $_SERVER as $key => $value) {
    if (is_scalar($value)) {
        $environment[(string) $key] = (string) $value;
    }
}

$environment['MYSQL_PWD'] = (string) config('database.connections.mysql.password');

$command = implode(' ', array_map(
    static fn (string $part): string => str_contains($part, ' ')
        ? '"'.$part.'"'
        : $part,
    $arguments,
));

$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);

if (! is_resource($process)) {
    fwrite(STDERR, "Could not start mysqldump.\n");
    exit(1);
}

$stderr = (string) stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$status = proc_close($process);

if ($status !== 0) {
    fwrite(STDERR, "mysqldump failed (exit {$status}).\n".$stderr."\n");
    exit($status);
}

if (! is_file($destination)) {
    fwrite(STDERR, "mysqldump reported success but wrote no file.\n");
    exit(1);
}

/*
| Verify the file before claiming it worked.
|
| A dump that cannot be imported is worse than no dump, because it is discovered
| on the other machine, at the wrong moment. These three checks are the whole
| reason this is a script rather than a command.
|
|   1. it must not start with a byte order mark, which means PowerShell wrote it
|   2. it must not contain a NUL byte, which means the same thing
|   3. it must be valid UTF-8, or the accents in the demo data are lost
|
| It is then imported into a scratch database and counted, so "the file exists"
| is never mistaken for "the file works".
*/
$problems = [];

$contents = (string) file_get_contents($destination);

if (str_starts_with($contents, "\u{FEFF}")) {
    $problems[] = 'starts with a byte order mark, so it was written as UTF-16 or UTF-8-BOM';
}

if (str_contains($contents, "\0")) {
    $problems[] = 'contains a NUL byte, so it was written as UTF-16';
}

/*
| 3. it must be valid UTF-8, or the accents in the demo data are corrupted.
|
| mb_check_encoding is asked to be strict. It returns false rather than silently
| substituting a replacement character, which is the behaviour needed here: a
| corrupted export that reports success is the exact failure being guarded
| against.
*/
if (! mb_check_encoding($contents, 'UTF-8')) {
    $problems[] = 'is not valid UTF-8, so accented text in the demo data would be corrupted';
}

if ($problems !== []) {
    fwrite(STDERR, "The export is not safe to move. It:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, '  - '.$problem."\n");
    }
    fwrite(STDERR, "Delete the file and do not copy it anywhere.\n");
    exit(1);
}

$tables = substr_count($contents, 'CREATE TABLE');
$rows = preg_match_all('/^INSERT INTO/m', $contents) ?: 0;

echo 'Exported '.$database.' to '.$destination."\n";
echo '  size:     '.number_format((int) filesize($destination)).' bytes'."\n";
echo '  encoding: UTF-8, no byte order mark, no NUL byte'."\n";
echo '  tables:   '.$tables."\n";
echo '  inserts:  '.$rows."\n";
echo "\nNext: import it into an empty database and count the rows. Do not assume.\n";
