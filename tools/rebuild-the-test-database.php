<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Drops and recreates one database, then migrates it.
 *
 * Written because `migrate:fresh` reported "Dropping all tables ... DONE" and
 * then failed on `Table 'job_batches' already exists`, twice, leaving the schema
 * worse each time. The drop step reported success and a table survived it, so
 * every subsequent run built on a half-wiped schema and the suite could not even
 * reach a foreign key on `users`.
 *
 * The database is recreated from nothing rather than wiped in place, so the
 * result does not depend on the drop step behaving. The name comes from the
 * command line and is checked against the test database named in `phpunit.xml`
 * before anything is dropped, because the failure mode of a script that drops
 * databases is that it is pointed at the wrong one.
 *
 * Credentials come from the application's own configuration. Nothing is printed.
 *
 * Usage: php tools/rebuild-the-test-database.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = 'lms_test';

$fromXml = @file_get_contents(__DIR__.'/../phpunit.xml');
$declared = preg_match('/name="DB_DATABASE"\s+value="([^"]+)"/', (string) $fromXml, $m) === 1
    ? $m[1]
    : null;

if ($declared !== $target) {
    fwrite(STDERR, "phpunit.xml names a different test database. Refusing to touch anything.\n");
    exit(1);
}

$config = config('database.connections.mysql');

if ($config['database'] === $target) {
    fwrite(STDERR, "The application is already pointed at the test database. Refusing.\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d', $config['host'], $config['port']),
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$name = '`'.str_replace('`', '', $target).'`';

$pdo->exec("DROP DATABASE IF EXISTS {$name}");
$pdo->exec("CREATE DATABASE {$name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

echo "  dropped and recreated {$target}\n";

/*
 | The migrate step has to be told which database it is building.
 |
 | `.env` names the demonstration database, so an unqualified `artisan migrate`
 | checks that one, finds it already migrated, and reports "Nothing to migrate"
 | while the database that was just recreated stands empty. That is what the first
 | run of this script did, and it is recorded here rather than left to be
 | rediscovered: a green line that means the wrong database was checked is worse
 | than an error.
 |
 | It is passed through `proc_open` with an explicit environment rather than
 | through a `set NAME=value &&` prefix. That prefix is a cmd construct; run from
 | PowerShell it silently becomes something else, and the second run of this
 | script reached the driver with a database name carrying a trailing space. The
 | environment is stated, not implied by whichever shell happens to be in front.
 */
/*
 | The child inherits this process's whole environment, with one variable changed.
 |
 | `$_ENV` is the obvious source and it is empty on this installation, because
 | `variables_order` does not include `E`. Handing `proc_open` an environment of
 | one entry therefore stripped `SystemRoot` and `PATH` from the child, and the
 | driver failed to connect with error 2002 rather than saying why. `getenv()`
 | with no argument returns the real environment whatever `variables_order` is set
 | to, which is what a child process actually needs.
 */
$environment = getenv();

if (! is_array($environment)) {
    fwrite(STDERR, "  this process reports no environment to pass on\n");
    exit(1);
}

$environment['DB_DATABASE'] = $target;

$process = proc_open(
    [PHP_BINARY, 'artisan', 'migrate', '--force'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    dirname(__DIR__),
    $environment
);

if (! is_resource($process)) {
    fwrite(STDERR, "  could not start the migrate step\n");
    exit(1);
}

echo stream_get_contents($pipes[1]);
echo stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);

$code = proc_close($process);

if ($code !== 0) {
    exit(1);
}

$count = $pdo
    ->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '.$pdo->quote($target))
    ->fetchColumn();

echo "  {$target} now has {$count} tables\n";

exit(0);
