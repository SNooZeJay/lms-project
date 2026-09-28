<?php

/*
 | Removes the rows the verification tools created.
 |
 | Both tools seed into the configured database so they can measure something
 | real. That is fine while they run and not fine afterwards: a database left
 | holding four hundred empty courses is a database that will confuse the next
 | person to look at a screenshot, and the verification numbers are worthless if
 | the state they were taken from is not the state anyone else sees.
 |
 | Only rows the tools made are removed. The seeded courses have no modules and
 | no lessons, which is how they are told apart from anything a person entered,
 | so this cannot delete real content.
 |
 | Run with: php tools/verify-cleanup.php
 */

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/*
 | A course with nothing in it cannot have been written by a person. Every real
 | course in this application has at least one module, and a module cannot exist
 | without the course being real content rather than a placeholder.
 */
$empty = Course::query()
    ->whereDoesntHave('modules')
    ->whereDoesntHave('lessons')
    ->pluck('id');

echo 'empty courses found: '.$empty->count()."\n";

$progressRows = 0;
$enrollments = 0;

foreach ($empty as $courseId) {
    $progressRows += LessonProgress::query()->whereIn(
        'enrollment_id',
        Enrollment::query()->where('course_id', $courseId)->pluck('id')
    )->delete();

    $enrollments += Enrollment::query()->where('course_id', $courseId)->delete();

    Lesson::query()->whereIn('module_id', Module::query()->where('course_id', $courseId)->pluck('id'))->delete();
    Module::query()->where('course_id', $courseId)->delete();
}

$deleted = Course::query()->whereIn('id', $empty)->delete();

echo "deleted {$deleted} courses, {$enrollments} enrollments, {$progressRows} progress rows\n";

echo "\nremaining:\n";
echo '  courses:   '.Course::count()."\n";
echo '  modules:   '.Module::count()."\n";
echo '  lessons:   '.Lesson::count()."\n";
echo '  enrolled:  '.Enrollment::count()."\n";
echo '  progress:  '.LessonProgress::count()."\n";
