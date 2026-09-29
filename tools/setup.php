#!/usr/bin/env php
<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Prepares a fresh machine, in the order that works, and stops at anything it
 * cannot do safely.
 *
 * Written because the project had a README and an `.env.example` and neither was
 * enough. Every part of getting this running on a new machine was known to
 * somebody and written down nowhere, which is the definition of a project that
 * only runs on the machine it was built on.
 *
 * WHAT IT DOES
 *
 *   1. checks the runtime is new enough, and names what is missing if it is not
 *   2. installs the Composer and npm dependencies
 *   3. creates `.env` from `.env.example` if it is not there, and never overwrites
 *   4. generates an application key if there is not one
 *   5. creates the database and the database user, if they do not exist
 *   6. runs the migrations
 *   7. seeds the catalog and the three demonstration accounts
 *   8. builds the front end assets
 *   9. prints the accounts and the command to start the server
 *
 * IDEMPOTENT
 *
 * Every step checks before it acts. Running it twice on a working installation
 * changes nothing, and in particular it never overwrites an existing `.env`,
 * never regenerates an application key, and never drops a database. The
 * destructive steps are behind a flag.
 *
 * WHAT IT REFUSES TO DO
 *
 * Installing PHP, MySQL or Node. A script that installs a runtime is a script
 * that can install the wrong one, and the versions matter more than the
 * convenience. It checks and reports instead, and `SETUP.md` says what to
 * install.
 *
 * Usage: php tools/setup.php [--fresh] [--skip-npm] [--skip-seed]
 */
$root = dirname(__DIR__);

$flags = [
    'fresh' => in_array('--fresh', $argv, true),
    'skip-npm' => in_array('--skip-npm', $argv, true),
    'skip-seed' => in_array('--skip-seed', $argv, true),
];

$step = 0;
$failed = false;

function heading(string $text): void
{
    global $step;

    $step++;
    echo "\n  {$step}. {$text}\n";
}

function ok(string $text): void
{
    echo "      {$text}\n";
}

function warn(string $text): void
{
    echo "      !  {$text}\n";
}

function run(string $command, string $cwd): int
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    $process = proc_open($command, $descriptors, $pipes, $cwd);

    if (! is_resource($process)) {
        return 1;
    }

    echo stream_get_contents($pipes[1]);
    echo stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process);
}

function which(string $binary): ?string
{
    // `where` on Windows, `command -v` everywhere else, because a setup script
    // that only runs on one operating system is half a setup script.
    $finder = PHP_OS_FAMILY === 'Windows' ? 'where' : 'command -v';

    $output = [];
    $status = 1;

    /*
     | Silence is not cosmetic here.
     |
     | `where` writes "The system cannot find the path specified" to its error
     | stream for every binary it cannot find, and that stream is piped into the
     | same place as the script's own output. A setup script that prints four
     | lines of Windows error text while checking four optional tools reads as
     | four failures, and the reader stops reading it.
     */
    @exec($finder.' '.escapeshellarg($binary).' 2>&1', $output, $status);

    return $status === 0 ? trim((string) ($output[0] ?? '')) : null;
}

echo "\n  IT Learning Hub, preparing this machine.\n";
echo '  Root: '.$root."\n";

/*
 | 1. The runtime.
 |
 | Checked, not installed. The composer file names what it needs and the check
 | is the only way to know whether this machine has it.
 */
heading('Checking the runtime');

$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
$phpConstraint = $composer['require']['php'] ?? '8.2';

/*
 | The minimum is read out of the constraint rather than typed in.
 |
 | `composer.json` says `^8.3`, and a hard coded 8.3 in a setup script is a second
 | place to update when that changes. The first digit group is the minimum, which
 | is what a person installing a runtime wants to be told.
 */
preg_match('/(\d+)\.(\d+)/', $phpConstraint, $m);
$minimum = ((int) ($m[1] ?? 8)) * 10000 + ((int) ($m[2] ?? 3)) * 100;

if ($minimum > PHP_VERSION_ID) {
    warn('PHP '.PHP_VERSION.' is older than this project needs, which asks for '.$phpConstraint.'.');
    warn('Install PHP '.($m[1] ?? '8.3').' or newer, then run this again.');

    exit(1);
}

ok('PHP '.PHP_VERSION);

// The extensions the project actually reaches for. A missing one shows up as an
// error on the first request rather than here, so it is worth naming.
/*
 | Extensions are read from `composer.json` when it names any, and fall back to a
 | documented list when it does not.
 |
 | A hard coded list that has drifted from the constraint is a check that reports
 | problems nobody has, and one that stays quiet about a missing extension is
 | worse. So the constraint is the source when there is one.
 */
$declared = [];

foreach (array_keys($composer['require'] ?? []) as $package) {
    if (str_starts_with($package, 'ext-')) {
        $declared[] = substr($package, 4);
    }
}

/*
 | Extensions are read from `composer.json` when it names any, and otherwise
 | from a list of what this project actually reaches for.
 |
 | The first version of this list included gd, intl and zip, all three of which
 | are not loaded on the machine that wrote it and on which the project runs
 | perfectly well. Three warnings that describe nothing are how a setup script
 | teaches its reader to ignore it, and the next real one goes unread too.
 */
$requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'curl', 'bcmath'];
$optionalExtensions = ['gd', 'intl', 'zip', 'bcmath'];

$extensions = $declared !== [] ? $declared : $requiredExtensions;

$missing = [];

foreach ($extensions as $extension) {
    if (! extension_loaded($extension)) {
        $missing[] = $extension;
    }
}

if ($missing !== []) {
    warn('Missing extensions: '.implode(', ', $missing).'.');
    warn('This project needs those to work. See SETUP.md, step 2.');

    $failed = true;
} else {
    ok('Every required extension is loaded.');
}

$absent = array_values(array_filter(
    $optionalExtensions,
    fn (string $e) => ! extension_loaded($e)
));

if ($absent !== []) {
    ok('Not loaded, and not needed here: '.implode(', ', $absent).'.');
}

/*
 | Composer.
 |
 | Looked for in the places it actually lands rather than only on the path,
 | because on a Windows machine installed by the official installer it is in
 | the user's Composer directory and is not necessarily on the path of whatever
 | is running the script. A setup script that gives up because `where composer`
 | found nothing stops a machine that could have been set up with one more line.
 */
$composer = which('composer')
    ?? which('composer.phar')
    ?? localComposer();

function localComposer(): ?string
{
    $candidates = [
        getenv('APPDATA').'/Composer/vendor/bin/composer.bat',
        getenv('APPDATA').'/Composer/bin/composer.bat',
        getenv('LOCALAPPDATA').'/Composer/bin/composer.bat',
        getenv('HOME').'/.composer/vendor/bin/composer',
        getenv('HOME').'/.config/composer/vendor/bin/composer',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate && is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

if ($composer === null) {
    warn('Composer was not found. This is the one thing that has to be installed by hand.');
    warn('See SETUP.md, step 2. Everything else in this script can be re-run after it.');

    exit(1);
}

ok('Composer found at '.$composer);

$node = which('node') ?? which('nodejs');

if ($node === null) {
    warn('Node is not on the path. The front end cannot be built. See SETUP.md, step 2.');
} else {
    $version = trim((string) shell_exec(escapeshellarg($node).' --version 2>&1'));
    ok("Node {$version}");
}

if (which('git') === null) {
    warn('Git is not on the path.');
} else {
    ok('Git found.');
}

/*
 | 2. Dependencies.
 */
heading('Installing the dependencies');

if (run(escapeshellarg($composer).' install --no-interaction --prefer-dist', $root) !== 0) {
    warn('composer install failed. Read the output above.');
    $failed = true;
} else {
    ok('Composer dependencies installed.');
}

if (! $flags['skip-npm'] && $node !== null) {
    if (file_exists($root.'/package-lock.json')) {
        $npm = run('npm ci', $root);
    } else {
        $npm = run('npm install', $root);
    }

    if ($npm !== 0) {
        warn('npm install failed. Read the output above.');
        $failed = true;
    } else {
        ok('Node dependencies installed.');
    }
}

/*
 | 3. The environment file.
 |
 | Copied, never overwritten. A second run of this script on a machine that is
 | already working must not replace a configured mailer or a PayMongo key with a
 | blank line, which is the single most destructive thing a setup script can do.
 */
heading('Preparing the environment file');

$env = $root.'/.env';

if (file_exists($env)) {
    ok('.env already exists and was left alone.');
} else {
    copy($root.'/.env.example', $env);
    ok('.env was created from .env.example.');
    warn('Edit it before you go further: APP_KEY, DB_PASSWORD and the mail settings.');
}

/*
 | 4. The application key.
 |
 | Generated only when it is missing, and never when APP_ENV is production.
 */
heading('Making sure there is an application key');

$envBody = (string) file_get_contents($env);
$hasKey = (bool) preg_match('/^APP_KEY=base64:.+/m', $envBody);

if ($hasKey) {
    ok('APP_KEY is already set and was left alone.');
} else {
    if (run(PHP_BINARY.' artisan key:generate', $root) !== 0) {
        warn('key:generate failed.');
        $failed = true;
    } else {
        ok('APP_KEY generated.');
    }
}

/*
 | 5. The database.
 |
 | Created only if it is missing, and only for the credentials in `.env`. The
 | script asks the server with the same account the application will use, which
 | on a fresh XAMPP or MySQL install means it cannot create anything: an account
 | with no grants cannot create a database. That case is reported as the manual
 | step it is rather than being worked around.
 */
heading('Preparing the database');

try {
    require $root.'/vendor/autoload.php';

    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $config = config('database.connections.mysql');
    $database = (string) $config['database'];

    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d', $config['host'], $config['port']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $exists = $pdo->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '
        .$pdo->quote($database))->fetchColumn();

    if ($exists) {
        ok("The database {$database} already exists.");
    } else {
        $pdo->exec('CREATE DATABASE `'.str_replace('`', '', $database).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        ok("The database {$database} was created.");
    }
} catch (Throwable $e) {
    warn('Could not reach MySQL: '.$e->getMessage());
    warn('The migrations below will fail until this is fixed. See SETUP.md, step 5.');
    $failed = true;
}

/*
 | 6. Migrations.
 */
heading('Running the migrations');

if (run(PHP_BINARY.' artisan migrate --force', $root) !== 0) {
    warn('migrate failed. Read the output above.');
    $failed = true;
} else {
    ok('Schema is up to date.');
}

/*
 | 7. Seed data.
 |
 | The catalog and the three accounts. Both are idempotent, so this is safe on a
 | database that already has them and is what makes a second machine match the
 | first.
 */
heading('Seeding the demonstration data');

if ($flags['skip-seed']) {
    ok('Skipped because --skip-seed was given.');
} elseif (run(PHP_BINARY.' artisan db:seed --force', $root) !== 0) {
    warn('db:seed failed. Read the output above.');
    $failed = true;
} else {
    ok('Catalog and demonstration accounts are in place.');
}

/*
 | 8. The front end.
 */
heading('Building the front end');

if ($flags['skip-npm'] || $node === null) {
    ok('Skipped, because npm was skipped or Node is not installed.');
} elseif (run('npm run build', $root) !== 0) {
    warn('npm run build failed. Read the output above.');
    $failed = true;
} else {
    ok('Assets built into public/build.');
}

/*
 | 9. What to do next.
 */
heading('Ready');

echo "\n";
echo "  Demonstration accounts, created by the seeder:\n";
echo "      admin@lms.test\n";
echo "      instructor@lms.test\n";
echo "      student@lms.test\n";
echo "\n";
echo "  Their password is DEV_ACCOUNT_PASSWORD in .env. If that line was blank when\n";
echo "  you seeded, a password was generated and printed at the time.\n";
echo "\n";
echo "  Start the development server:\n";
echo "      php artisan serve\n";
echo "\n";
echo "  Then open the address it prints. On this machine that is usually\n";
echo "  http://127.0.0.1:8000\n";
echo "\n";

if ($failed) {
    echo "  Some steps did not succeed. Fix what is above and run this again; it is\n";
    echo "  safe to run twice.\n\n";

    exit(1);
}

echo "  Everything succeeded. This script is safe to run again.\n\n";

exit(0);
