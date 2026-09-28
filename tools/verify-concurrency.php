<?php

/*
 | A real concurrency probe, outside PHPUnit.
 |
 | The test suite runs one process on one connection, so it can show that the
 | second request converges, but not that two connections interleaving part way
 | through a statement are safe. That is the claim the row locks exist to
 | support, so it is worth proving directly.
 |
 | Connection A is the application. Connection B stands in for a second request
 | that has already read the row and is holding it while it works. A then tries
 | to reserve the next position. If the lock is doing its job, A cannot get a
 | clean read of the maximum, and two concurrent adds can never choose the same
 | number.
 |
 | Run with: php tools/verify-concurrency.php
 |
 | It is a tool rather than a test because it deliberately holds a lock open and
 | lets it time out, which a test run should never do to a database that other
 | work might be using.
 |
 | One trap worth naming, because it makes this probe silently useless: asking
 | the manager for the default connection by name hands back the same object.
 | A "second" connection that is really the same session never blocks on a lock
 | it already holds, so the probe measures nothing and still prints a result. The
 | second connection below is therefore a second configuration, which forces a
 | second PDO and a second database session.
 |
 | Everything it writes is deleted before it exits.
 */

use App\Actions\Courses\Curriculum\CreateModule;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Support\Position;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Throwable;

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$driver = (string) config('database.default');

config(['database.connections.probe' => config('database.connections.'.$driver)]);

$instructor = User::query()->whereHas('profile', fn ($query) => $query->where('role', 'instructor'))->first();

if ($instructor === null) {
    fwrite(STDERR, "No instructor exists. Seed the database first.\n");
    exit(1);
}

$pass = true;
$say = function (string $line) use (&$pass): void {
    echo $line."\n";
};

// The probe course is created outside any transaction, so the application
// connection holds no lock on it before the test starts.
$course = Course::factory()->create([
    'instructor_id' => $instructor->id,
    'title' => 'Concurrency probe '.uniqid(),
]);

$say('connection: '.$driver);
$say('probe course: #'.$course->id);
$say('');

$b = DB::connection('probe');

// Three seconds is long enough to prove a wait and short enough to report one.
$b->statement('SET SESSION innodb_lock_wait_timeout = 3');

$b->beginTransaction();
$b->table('courses')->where('id', $course->id)->lockForUpdate()->first();

$say('connection B  holds the parent row lock');

$started = microtime(true);
$refused = null;

try {
    DB::transaction(function () use ($course): void {
        Position::reserve($course, $course->modules());
    });
} catch (Throwable $exception) {
    $refused = $exception;
}

$waited = (microtime(true) - $started) * 1000;

$say('connection A  waited '.round($waited).'ms');
$say('');

if ($refused !== null) {
    $say('  A could not read the maximum: '.trim(strtok($refused->getMessage(), "\n")));
    $say('  PASS  the lock stopped A. Two concurrent adds cannot choose the same position.');
} elseif ($waited >= 2500) {
    $say('  PASS  A blocked on the lock for the whole timeout.');
} else {
    $say('  FAIL  A read the maximum while B still held the lock.');
    $pass = false;
}

// Release the other session, then confirm nothing was consumed.
$b->rollBack();
$b->disconnect();

$position = DB::transaction(fn () => Position::reserve($course, $course->modules()));

$say('');
$say('after release, next free position: '.$position);

if ($position === 1) {
    $say('  PASS  the blocked attempt consumed nothing, so numbering did not skip.');
} else {
    $say('  FAIL  expected 1. A refused attempt burned a position.');
    $pass = false;
}

// A module added normally, to show the happy path still works end to end.
DB::transaction(function () use ($course, $instructor): void {
    app(CreateModule::class)
        ->handle($instructor, $course, ['title' => 'Probe module']);
});

$say('');
$say('modules after a normal add: '.Module::query()->where('course_id', $course->id)->count()
    .' at position '.Module::query()->where('course_id', $course->id)->value('position'));

// Clean up. The probe must not leave a course behind for the next run to trip
// over, and the report is worthless if it changes the state it is measuring.
Module::query()->where('course_id', $course->id)->delete();
$course->delete();

$say('');
$say($pass ? 'RESULT  pass' : 'RESULT  fail');
$say('cleaned up. nothing was left behind.');

exit($pass ? 0 : 1);
