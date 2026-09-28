<?php

/*
 | Does the catalog stay usable when there is real data in it?
 |
 | A page that is fine with five courses and slow with five hundred is the most
 | common way an application turns out to have been designed only against a
 | demonstration dataset. This seeds a few hundred published courses and then
 | times the public pages that a visitor actually lands on.
 |
 | The point is not a benchmark number. It is that the query count on each page
 | stays the same whatever the data volume, because a count that grows is a page
 | that will not survive a real catalog.
 |
 | Run with: php tools/verify-large-dataset.php
 |
 | It writes to the configured database, so it is a tool and not a test. Set
 | SKIP_SEED=1 to measure what is already there.
 */

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\ProgressCalculator;
use App\Services\Reporting\OperationsReport;
use App\Support\PublishedCourses;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$seed = (string) getenv('SKIP_SEED') !== '1';

$queries = [];
$counting = false;

DB::listen(function ($query) use (&$queries, &$counting): void {
    if ($counting) {
        $queries[] = $query->sql;
    }
});

$measure = function (string $label, callable $work): void {
    global $queries, $counting;

    $queries = [];
    $counting = true;
    $started = microtime(true);
    $work();
    $elapsed = (microtime(true) - $started) * 1000;
    $counting = false;

    printf("  %-34s %4d queries  %7.1f ms\n", $label, count($queries), $elapsed);
};

echo 'courses: '.Course::count().'  modules: '.Module::count().'  lessons: '.Lesson::count()."\n";
echo 'enrollments: '.Enrollment::count().'  progress rows: '.LessonProgress::count()."\n\n";

if ($seed) {
    $started = microtime(true);
    $instructor = User::query()->first();

    Course::factory()->count(200)->create([
        'instructor_id' => $instructor->id,
        'status' => CourseStatus::Published,
        'course_type' => CourseType::Free,
        'price_minor' => 0,
    ]);

    echo 'seeded 200 more in '.round((microtime(true) - $started) * 1000)."ms\n\n";

    /*
     | Enrollments and progress as well, because the two paths that matter most
     | read a student's own history. Measuring them against zero rows reports a
     | single query and proves nothing, which is the exact trap this tool is
     | here to avoid.
     */
    $started = microtime(true);

    $student = User::query()->whereHas('profile', fn ($q) => $q->where('role', 'student'))->first();

    if ($student === null) {
        fwrite(STDERR, "No student exists. Seed the database first.\n");
        exit(1);
    }

    // Only courses this student is not already in. Re-running the tool must be
    // harmless, and the unique (student_id, course_id) rule would otherwise stop
    // it with an integrity error rather than a sentence explaining why.
    $published = Course::query()
        ->where('status', CourseStatus::Published)
        ->whereDoesntHave('enrollments', fn ($q) => $q->where('student_id', $student->id))
        ->with('modules')
        ->limit(15)
        ->get();

    if ($published->isEmpty()) {
        echo "  already enrolled in every published course; nothing to add\n\n";
    }

    foreach ($published as $course) {
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        foreach ($course->modules as $module) {
            $module->forceFill(['status' => ContentStatus::Published])->save();

            $lessonIds = Lesson::query()
                ->where('module_id', $module->id)
                ->limit(10)
                ->pluck('id');

            foreach ($lessonIds as $lessonId) {
                LessonProgress::factory()->completed()->create([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $student->id,
                    'lesson_id' => $lessonId,
                ]);
            }
        }
    }

    echo 'seeded '.$published->count().' enrollments in '.round((microtime(true) - $started) * 1000)."ms\n\n";
}

echo "query count per page, at this volume\n";

$measure('catalog index (paginated)', fn () => Course::query()
    ->where('status', CourseStatus::Published)
    ->with('instructor:id,name')
    ->paginate(12));

$measure('home page groups', fn () => app(PublishedCourses::class)->forHomePage());

$student = User::query()->whereHas('profile', fn ($q) => $q->where('role', 'student'))->first();

if ($student !== null) {
    $measure('student dashboard counts', fn () => app(OperationsReport::class)->forStudent($student));
    $measure('student average progress', fn () => app(OperationsReport::class)->averageStudentProgress($student));
    $measure('student agenda', fn () => app(OperationsReport::class)->studentAgenda($student, 6));
    $measure('admin enrollment report (50)', fn () => app(OperationsReport::class)->enrollmentRows(50));
    $measure('admin counters', fn () => app(OperationsReport::class)->forAdministrator());
}

// The one that must not grow: progress is read in a single batched pass, so a
// student with many courses costs the same as a student with one.
$busy = User::query()->whereHas('enrollments')->first();

if ($busy !== null) {
    $count = Enrollment::query()->where('student_id', $busy->id)->count();

    echo "\n  a student with {$count} enrollments\n";

    $measure('  their progress, batched', fn () => app(ProgressCalculator::class)->forEnrollments(
        Enrollment::query()->where('student_id', $busy->id)->get()
    ));
}

echo "\ndone.\n";
