<?php

use App\Actions\Completion\CompleteCourse;
use App\Actions\Quizzes\StartQuizAttempt;
use App\Actions\Quizzes\SubmitQuizAttempt;
use App\Models\Enrollment;
use App\Services\Learning\CourseCompletionChecker;
use Illuminate\Contracts\Console\Kernel;

/*
 | Gives the demonstration one Student who has finished something.
 |
 | WHY THIS EXISTS
 |
 | Completion needs every required lesson finished and every required published quiz
 | passed. Two enrollments in this database have every lesson finished, nobody has
 | passed a quiz, and so no certificate exists. Every part of that is correct: the
 | checker asked for the quizzes and the checker was right to.
 |
 | The consequence is that the flagship workflow cannot be shown. A presenter can
 | open the Student area, finish nothing, see no quiz result, no score, no pass, and
 | no certificate, and conclude the feature is missing. It is built, tested and
 | correct. It simply has nothing to show.
 |
 | WHY IT RUNS THE ACTIONS RATHER THAN INSERTING ROWS
 |
 | Writing an attempt straight into the table would produce a database that looks
 | right and proves nothing. Grading, the quiz notifications to the Student, the
 | activity notice to the Instructor, the eligibility re-check and the certificate
 | code are all things the application does. Seeding past them would leave the one
 | workflow nobody has ever watched running still unwatched, which is the thing
 | most worth watching.
 |
 | So this opens each attempt with StartQuizAttempt, submits the correct answers
 | with SubmitQuizAttempt, and completes the course with CompleteCourse. Every rule
 | those enforce is enforced here: the attempt limit, "no new attempt after a
 | pass", the eligibility rules, and the one certificate per enrollment rule.
 |
 | It is safe to run twice. A quiz already passed is left alone, and completion
 | returns the existing certificate rather than issuing a second one.
 |
 | Run with: php tools/seed-completion-demo.php [--enrollment=ID] [--dry]
 */

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = [];
$dry = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--dry') {
        $dry = true;
    } elseif (preg_match('/^--enrollment=(\d+)$/', $argument, $found)) {
        $options['enrollment'] = (int) $found[1];
    }
}

function say(string $text = ''): void
{
    echo '  '.$text.PHP_EOL;
}

$start = $app->make(StartQuizAttempt::class);
$submit = $app->make(SubmitQuizAttempt::class);
$complete = $app->make(CompleteCourse::class);
$checker = $app->make(CourseCompletionChecker::class);

say('=== what the demonstration has now ===');

$enrollments = Enrollment::query()
    ->with(['course', 'student'])
    ->when(isset($options['enrollment']), fn ($query) => $query->whereKey($options['enrollment']))
    ->orderBy('id')
    ->get();

foreach ($enrollments as $enrollment) {
    $verdict = $checker->check($enrollment);
    say(sprintf(
        'enrollment #%d  %-40s %-14s lessons %2d/%-2d quizzes %d/%-2d %s',
        $enrollment->id,
        substr((string) $enrollment->course->title, 0, 38),
        $enrollment->status->value,
        $verdict['lessons_completed'],
        $verdict['lessons_total'],
        $verdict['quizzes_passed'],
        $verdict['quizzes_total'],
        $verdict['eligible'] ? 'eligible' : 'not eligible'
    ));
    foreach ($verdict['reasons'] as $reason) {
        say('    still to do: '.$reason);
    }
}

$target = $enrollments->first(fn ($enrollment) => $checker->check($enrollment)['eligible'] === false
    && $enrollment->status->value === 'active'
    && $checker->check($enrollment)['lessons_total'] > 0
    && $checker->check($enrollment)['lessons_completed'] >= $checker->check($enrollment)['lessons_total']);

if ($target === null) {
    say();
    say('Nothing to do: no active enrollment has every lesson finished without a certificate.');
    say();

    return;
}

say();
say('=== the one this will finish ===');
say(sprintf(
    'enrollment #%d  %s  for %s',
    $target->id,
    $target->course->title,
    $target->student->name
));

if ($dry) {
    say();
    say('That is the dry run. Nothing was written.');
    say();

    return;
}

$quizzes = $target->course->quizzes()->where('status', 'published')->get();

foreach ($quizzes as $quiz) {
    $alreadyPassed = $quiz->attempts()->where('student_id', $target->student_id)->where('passed', true)->exists();

    if ($alreadyPassed) {
        say(sprintf('quiz #%d  %-44s already passed, left alone', $quiz->id, substr((string) $quiz->title, 0, 42)));

        continue;
    }

    $attempt = $start->handle($target->student, $quiz);

    // Answer every question correctly. The grader reads the stored options, so
    // this is the same answer a student would give, not a score written in.
    $answers = [];
    foreach ($quiz->questions()->with('options')->get() as $question) {
        $correct = $question->options->firstWhere('is_correct', true);
        if ($correct === null) {
            say(sprintf('quiz #%d  question %d has no correct option, so it cannot be passed', $quiz->id, $question->id));

            continue 2;
        }
        $answers[$question->id] = $correct->id;
    }

    $result = $submit->handle($target->student, $quiz, $attempt, $answers);

    say(sprintf(
        'quiz #%d  %-44s %s at %s%%',
        $quiz->id,
        substr((string) $quiz->title, 0, 42),
        $result->passed ? 'passed' : 'FAILED',
        $result->score_percent
    ));
}

$verdict = $checker->check($target->refresh());

if (! $verdict['eligible']) {
    say();
    say('Still not eligible after the quizzes, so no certificate was issued:');
    foreach ($verdict['reasons'] as $reason) {
        say('    '.$reason);
    }
    say();

    return;
}

$outcome = $complete->handle($target->student, $target->refresh());
$certificate = $outcome['certificate'];

say();
say('=== the result ===');
say('enrollment status : '.$outcome['enrollment']->status->value);
say('completed at      : '.$outcome['enrollment']->completed_at);
say('certificate       : '.$certificate->certificate_code);
say('issued            : '.$certificate->created_at);
say('already existed   : '.($outcome['created'] ? 'no, this was a new one' : 'yes, nothing was duplicated'));
say();
say('The Student can now see the certificate at /student/certificates, and the claim');
say('button appears on the course page for anyone who reaches the same state.');
say();
