<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Removes the rows that the audit and probe tools leave behind.
 *
 * Written after the probes were found to have added eighteen courses to the
 * demonstration database. The catalog sorts by title, so "Audit course for
 * administrator" and "Somebody else course 8d791b" sat at the top of the first
 * page, and a demonstration opened on a list of fixtures with no cover images
 * rather than on the five real courses.
 *
 * The tools that created the rows are the tools that should not have created
 * them: an audit that changes the thing it audits is not an audit. Both probe
 * scripts now clean up after themselves, and this exists to remove what earlier
 * runs left.
 *
 * It only removes rows it can positively identify. A course is a fixture when its
 * slug begins with one of the prefixes the probes use, or its title carries the
 * marker they set. Nothing is matched on age or on a "looks temporary" test,
 * because deleting a demonstration course because its title looked unusual is
 * not a trade worth making.
 *
 * It prints what it is about to remove and removes nothing with --dry.
 *
 * Usage: php tools/remove-the-audit-fixtures.php [--dry]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dry = in_array('--dry', $argv, true);

/*
 | The markers the two probe tools use.
 |
 | Read out of the tools themselves would be better and is not possible, so they
 | are written here and the tools are expected to keep to them. Both probes
 | already use `audit-` in the slug, which is the prefix that matters.
 */
$slugPrefixes = ['audit-course-%', 'route-audit-%'];
$titleMarkers = ['Audit course for %', 'Route audit %', 'Somebody else course %'];

$matches = function () use ($slugPrefixes, $titleMarkers) {
    $query = DB::table('courses');

    foreach ($slugPrefixes as $prefix) {
        $query->orWhere('slug', 'like', $prefix);
    }

    foreach ($titleMarkers as $marker) {
        $query->orWhere('title', 'like', $marker);
    }

    return $query->get(['id', 'title', 'slug']);
};

$fixtures = $matches();

echo "\n  Found ".count($fixtures)." fixture course(s) from the audit tools:\n";

foreach ($fixtures as $course) {
    echo "    {$course->id}  {$course->title}  [{$course->slug}]\n";
}

/*
 | Anything left behind that is not a course.
 |
 | The probes also made accounts, enrollments and certificates. A course is
 | removed by cascade where the schema says so and by restriction where it does
 | not, so the dependents have to go first or the delete is refused and the tool
 | reports a constraint error instead of a clean result.
 */
$courseIds = $fixtures->pluck('id')->all();

/*
 | The rows that have to go before a course can.
 |
 | Read off the live schema rather than off the migrations, because the two
 | disagree about what is reachable from a course. `learning_materials` and
 | `lesson_progress` hang off lessons and enrollments, not off `course_id`, and a
 | delete that assumes otherwise silently removes nothing.
 |
 | Each is collected first and deleted afterwards, because the queries nest and
 | a delete invalidates the subquery it would have used.
 */
$dependents = [
    'quiz_answers' => DB::table('quiz_attempts')
        ->whereIn('quiz_id', fn ($q) => $q->select('id')->from('quizzes')->whereIn('course_id', $courseIds))
        ->pluck('id')->all(),
    'quiz_attempts' => DB::table('quiz_attempts')
        ->whereIn('quiz_id', fn ($q) => $q->select('id')->from('quizzes')->whereIn('course_id', $courseIds))
        ->pluck('id')->all(),
    'quiz_options' => DB::table('quiz_options')
        ->whereIn('question_id', fn ($q) => $q->select('id')->from('quiz_questions')
            ->whereIn('quiz_id', fn ($q2) => $q2->select('id')->from('quizzes')->whereIn('course_id', $courseIds)))
        ->pluck('id')->all(),
    'quiz_questions' => DB::table('quiz_questions')
        ->whereIn('quiz_id', fn ($q) => $q->select('id')->from('quizzes')->whereIn('course_id', $courseIds))
        ->pluck('id')->all(),
    'quizzes' => DB::table('quizzes')->whereIn('course_id', $courseIds)->pluck('id')->all(),
    'certificates' => DB::table('certificates')->whereIn('course_id', $courseIds)->pluck('id')->all(),
    'lesson_progress' => DB::table('lesson_progress')
        ->whereIn('enrollment_id', fn ($q) => $q->select('id')->from('enrollments')->whereIn('course_id', $courseIds))
        ->pluck('id')->all(),
    'enrollments' => DB::table('enrollments')->whereIn('course_id', $courseIds)->pluck('id')->all(),
    'learning_materials' => DB::table('learning_materials')
        ->whereIn('lesson_id', fn ($q) => $q->select('id')->from('lessons')
            ->whereIn('module_id', fn ($q2) => $q2->select('id')->from('modules')->whereIn('course_id', $courseIds)))
        ->pluck('id')->all(),
    'lessons' => DB::table('lessons')
        ->whereIn('module_id', fn ($q) => $q->select('id')->from('modules')->whereIn('course_id', $courseIds))
        ->pluck('id')->all(),
    'modules' => DB::table('modules')->whereIn('course_id', $courseIds)->pluck('id')->all(),
];

echo "\n  Dependents that must go first:\n";

foreach ($dependents as $label => $ids) {
    echo "    {$label}: ".count($ids)." row(s)\n";
}

if ($dry) {
    echo "\n  Nothing was removed. Re-run without --dry to remove them.\n\n";

    exit(0);
}

DB::transaction(function () use ($courseIds, $dependents): void {
    /*
     | Deleted in dependency order, deepest first, using the identifiers already
     | collected rather than re-querying: a delete invalidates the subquery a
     | second query would have used.
     */
    foreach (['quiz_answers', 'quiz_attempts', 'quiz_options', 'quiz_questions', 'quizzes',
        'certificates', 'lesson_progress', 'enrollments', 'learning_materials', 'lessons', 'modules'] as $table) {
        $ids = $dependents[$table] ?? [];
        if ($ids === []) {
            continue;
        }
        DB::table($table)->whereIn('id', $ids)->delete();
    }
    DB::table('courses')->whereIn('id', $courseIds)->delete();
});

echo "\n  Removed.\n";

$remaining = DB::table('courses')->count();

echo '  Courses now: '.$remaining."\n";

foreach (DB::table('courses')->orderBy('id')->get(['id', 'title']) as $course) {
    echo "    {$course->id}  {$course->title}\n";
}

echo "\n";

exit(0);
