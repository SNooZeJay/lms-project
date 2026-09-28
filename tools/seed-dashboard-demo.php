<?php

/*
 | Seeds a realistic dataset so the role dashboards can be seen populated, then
 | removes exactly what it added.
 |
 | The development database has five courses but no certificates, no payments and
 | no quiz attempts, so the dashboards render almost entirely empty states. That
 | is the truth of the current data, but it is a poor picture of the layout being
 | judged, because a dashboard is mostly its populated state.
 |
 | So this creates a demo set, records the highest key it reached on every table
 | it touched, and the cleanup pass deletes everything above those marks. Rows
 | created concurrently by a person are therefore never touched, and the
 | original rows are not touched at all.
 |
 | Run with:   php tools/seed-dashboard-demo.php
 |             php tools/seed-dashboard-demo.php --clean
 */

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$markFile = __DIR__.'/.dashboard-demo-marks.json';
$clean = in_array('--clean', $argv, true);
$purge = in_array('--purge', $argv, true);

/*
 | How a demo row is recognised.
 |
 | A demo course is identified by the marker sentence in its description, never
 | by its title. An earlier version of this tool matched on title and destroyed a
 | real catalog course called "Web Development Fundamentals", because the demo
 | set used the same title. A title is a thing a person chooses and can collide;
 | a marker sentence written into the description by this tool cannot.
 */
$demoMarker = 'A demonstration course';

/*
 | Not every table has an "id". course_requirements is keyed on course_id,
 | one row per course, so the mark and the cleanup both use the real key.
 */
$keyColumn = ['course_requirements' => 'course_id'];

/* Children before parents, so a foreign key never blocks its own removal. */
$order = [
    'quiz_answers', 'quiz_options', 'quiz_questions', 'quiz_attempts', 'quizzes',
    'payment_events', 'payments', 'certificates', 'lesson_progress',
    'learning_materials', 'activity_logs', 'lessons', 'modules',
    'enrollments', 'course_requirements', 'courses', 'users',
];

if (in_array('--orphans', $argv, true)) {
    /*
     | Accounts that own no course, hold no enrollment, and appear in no
     | activity log. These are what QuizFactory's default created_by leaves
     | behind: each quiz it makes without an explicit author gets a brand new
     | instructor nobody ever references.
     |
     | The three accounts this project actually signs in with are never
     | candidates, because the seeder that made them gives them real content.
     */
    /*
     | The accounts this project actually signs in with are never candidates,
     | because the seeder that made them gives them real content.
     |
     | They are found as the first holder of each development role rather than by
     | address. A list of addresses is a list that goes stale the first time a
     | person is given a new one, and it fails quietly: the script still runs,
     | and quietly picks a real instructor as an orphan to delete.
     */
    $keep = DB::table('users')
        ->whereIn('id', function ($query) {
            $query->selectRaw('MIN(user_id)')
                ->from('profiles')
                ->whereIn('role', ['administrator', 'instructor', 'student'])
                ->groupBy('role');
        })
        ->pluck('id')
        ->all();

    $candidates = DB::table('users')
        ->whereNotIn('id', $keep)
        ->whereNotIn('id', fn ($q) => $q->select('instructor_id')->from('courses'))
        ->whereNotIn('id', fn ($q) => $q->select('student_id')->from('enrollments'))
        ->whereNotIn('id', fn ($q) => $q->select('actor_id')->from('activity_logs'))
        ->whereNotIn('id', fn ($q) => $q->select('target_user_id')->from('activity_logs'))
        ->orderBy('id')
        ->get(['id', 'name', 'email']);

    fwrite(STDERR, sprintf("\n  %d unreferenced accounts\n\n", $candidates->count()));

    foreach ($candidates as $u) {
        fwrite(STDERR, sprintf("  %4d  %-32s %s\n", $u->id, mb_substr($u->name, 0, 32), $u->email));
    }

    if (! in_array('--yes', $argv, true)) {
        fwrite(STDERR, "\n  nothing removed. add --yes to delete the list above.\n");

        exit(0);
    }

    $ids = $candidates->pluck('id')->all();

    if ($ids !== []) {
        DB::table('users')->whereIn('id', $ids)->delete();
    }

    fwrite(STDERR, sprintf("\n  removed %d accounts\n", count($ids)));

    exit(0);
}

if ($purge) {
    /*
     | Remove every demo row by identity rather than by id. This is the mode to
     | use after a run that crashed part way through, because such a run leaves
     | rows below the recorded marks and --clean cannot see them.
     |
     | Children are removed before parents, and a parent is only removed once its
     | children are gone, so no foreign key blocks its own row.
     */
    $courseIds = DB::table('courses')->where('description', 'like', $demoMarker.'%')->pluck('id')->all();
    $userIds = DB::table('users')->where('name', 'like', 'Demo%')->pluck('id')->all();

    $wipe = [
        'quiz_answers' => fn () => DB::table('quiz_answers')
            ->whereIn('attempt_id', DB::table('quiz_attempts')
                ->where(fn ($q) => $q->whereIn('quiz_id', DB::table('quizzes')->whereIn('course_id', $courseIds)->pluck('id'))
                    ->orWhereIn('enrollment_id', DB::table('enrollments')->whereIn('course_id', $courseIds)->pluck('id')))->pluck('id'))
            ->delete(),
        'quiz_options' => fn () => DB::table('quiz_options')
            ->whereIn('question_id', DB::table('quiz_questions')
                ->whereIn('quiz_id', DB::table('quizzes')->whereIn('course_id', $courseIds)->pluck('id'))->pluck('id'))->delete(),
        'quiz_questions' => fn () => DB::table('quiz_questions')
            ->whereIn('quiz_id', DB::table('quizzes')->whereIn('course_id', $courseIds)->pluck('id'))->delete(),
        'quiz_attempts' => fn () => DB::table('quiz_attempts')
            ->whereIn('quiz_id', DB::table('quizzes')->whereIn('course_id', $courseIds)->pluck('id'))->delete(),
        'quizzes' => fn () => DB::table('quizzes')->whereIn('course_id', $courseIds)->delete(),
        'payment_events' => fn () => DB::table('payment_events')
            ->whereIn('payment_id', DB::table('payments')->whereIn('course_id', $courseIds)->pluck('id'))->delete(),
        'payments' => fn () => DB::table('payments')->whereIn('course_id', $courseIds)->delete(),
        'certificates' => fn () => DB::table('certificates')->whereIn('course_id', $courseIds)->delete(),
        'lesson_progress' => fn () => DB::table('lesson_progress')
            ->whereIn('enrollment_id', DB::table('enrollments')->whereIn('course_id', $courseIds)->pluck('id'))->delete(),
        'activity_logs' => fn () => DB::table('activity_logs')
            ->where(fn ($q) => $q->whereIn('actor_id', $userIds)->orWhereIn('target_user_id', $userIds))->delete(),
        'learning_materials' => fn () => DB::table('learning_materials')
            ->whereIn('lesson_id', DB::table('lessons')
                ->whereIn('module_id', DB::table('modules')->whereIn('course_id', $courseIds)->pluck('id'))->pluck('id'))->delete(),
        'lessons' => fn () => DB::table('lessons')
            ->whereIn('module_id', DB::table('modules')->whereIn('course_id', $courseIds)->pluck('id'))->delete(),
        'modules' => fn () => DB::table('modules')->whereIn('course_id', $courseIds)->delete(),
        'enrollments' => fn () => DB::table('enrollments')->whereIn('course_id', $courseIds)->delete(),
        'course_requirements' => fn () => DB::table('course_requirements')->whereIn('course_id', $courseIds)->delete(),
        'courses' => fn () => DB::table('courses')->where('description', 'like', $demoMarker.'%')->delete(),
        'users' => fn () => DB::table('users')->where('name', 'like', 'Demo%')->delete(),
    ];

    fwrite(STDERR, sprintf("\n  purging %d demo courses and %d demo accounts\n\n", count($courseIds), count($userIds)));

    foreach ($wipe as $table => $run) {
        fwrite(STDERR, sprintf("  %-20s removed %d\n", $table, $run()));
    }

    @unlink($markFile);

    fwrite(STDERR, "\n  state after purge\n");

    foreach (['users', 'courses', 'modules', 'lessons', 'enrollments', 'lesson_progress', 'certificates', 'payments', 'quiz_attempts'] as $table) {
        fwrite(STDERR, sprintf("    %-16s %d\n", $table, DB::table($table)->count()));
    }

    exit(0);
}

if ($clean) {
    if (! file_exists($markFile)) {
        fwrite(STDERR, "No marks recorded, so there is nothing this tool created to remove.\n");
        exit(0);
    }

    $marks = json_decode((string) file_get_contents($markFile), true);

    foreach ($order as $table) {
        $before = (int) ($marks[$table] ?? 0);

        if ($before <= 0) {
            continue;
        }

        $key = $keyColumn[$table] ?? 'id';
        $deleted = DB::table($table)->where($key, '>', $before)->delete();

        fwrite(STDERR, sprintf("  %-20s removed %d\n", $table, $deleted));
    }

    @unlink($markFile);

    fwrite(STDERR, "\n  state after cleanup\n");

    foreach (['users', 'courses', 'modules', 'lessons', 'enrollments', 'lesson_progress', 'certificates', 'payments', 'quiz_attempts'] as $table) {
        fwrite(STDERR, sprintf("    %-16s %d\n", $table, DB::table($table)->count()));
    }

    exit(0);
}

/* ------------------------------------------------------------------- seed */

$marks = [];

foreach ($order as $table) {
    $marks[$table] = (int) DB::table($table)->max($keyColumn[$table] ?? 'id');
}

$student = User::factory()->create(['name' => 'Demo Student']);
$otherStudent = User::factory()->create(['name' => 'Demo Student Two']);
$instructor = User::factory()->instructor()->create(['name' => 'Demo Instructor']);

$free = CourseType::Free->value;
$paid = CourseType::Paid->value;
$beginner = CourseLevel::Beginner->value;
$intermediate = CourseLevel::Intermediate->value;
$advanced = CourseLevel::Advanced->value;
$published = ContentStatus::Published->value;
$coursePublished = CourseStatus::Published->value;
$quizPublished = QuizStatus::Published->value;
$multipleChoice = QuestionType::MultipleChoice->value;
$attemptPassed = QuizAttemptStatus::Passed->value;
$attemptFailed = QuizAttemptStatus::Failed->value;
$lessonCompleted = LessonProgressStatus::Completed->value;
$enrollmentActive = EnrollmentStatus::Active->value;
$enrollmentCompleted = EnrollmentStatus::Completed->value;
$pendingPayment = EnrollmentStatus::PendingPayment->value;
$paymentPaid = PaymentStatus::Paid->value;
$certificateIssued = CertificateStatus::Issued->value;

$lessonNames = ['Getting started', 'Core concepts', 'In practice', 'Common mistakes', 'Review', 'Going further'];

/*
 | Demo course titles. Each carries a "(demo)" suffix so a demo course can never
 | be mistaken for a catalog course by a person reading the admin list, and so
 | the two sets of titles can never be confused for one another.
 */
$courseSpecs = [
    ['Web Development Fundamentals (demo)', $free, 0, $beginner, 'Programming', 6, 3],
    ['Networking Essentials (demo)', $paid, 125000, $intermediate, 'Networking', 5, 4],
    ['Database Design (demo)', $free, 0, $intermediate, 'Computing', 4, 2],
    ['Cybersecurity Basics (demo)', $paid, 99000, $beginner, 'Security', 4, 1],
    ['Systems Administration (demo)', $free, 0, $advanced, 'Networking', 3, 0],
];

foreach ($courseSpecs as $index => [$title, $type, $price, $level, $category, $lessonCount, $completedCount]) {
    $course = Course::factory()->for($instructor, 'instructor')->create([
        'title' => $title,
        'status' => $coursePublished,
        'course_type' => $type,
        'price_minor' => $price,
        'level' => $level,
        'category' => $category,
        'description' => $demoMarker.', present only so the role dashboards can be seen with realistic content in them. It is not part of the course catalog.',
        'learning_objectives' => 'Understand the core ideas, then apply them to a small practical problem.',
    ]);

    DB::table('course_requirements')->insert([
        'course_id' => $course->id,
        'require_all_lessons' => true,
        'minimum_lesson_percent' => null,
        'require_required_quizzes' => true,
        'require_passing_score' => true,
        'certificate_enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $lessons = [];

    for ($i = 1; $i <= $lessonCount; $i++) {
        $modulePosition = (int) ceil($i / 2);

        // The model keeps course_id and position out of $fillable, so they are
        // written through forceFill rather than trusted to mass assignment.
        $module = Module::query()
            ->where('course_id', $course->id)
            ->where('position', $modulePosition)
            ->first();

        if (! $module) {
            $module = new Module;
            $module->forceFill([
                'course_id' => $course->id,
                'position' => $modulePosition,
                'title' => "Module {$modulePosition}",
                'description' => 'A group of related lessons.',
                'status' => $published,
            ]);
            $module->save();
        }

        $lessons[] = Lesson::factory()->create([
            'module_id' => $module->id,
            'position' => $i,
            'title' => "Lesson {$i}: ".$lessonNames[$i - 1],
            'status' => $published,
            'is_required' => true,
        ]);
    }

    $quiz = Quiz::factory()->for($course, 'course')->create([
        // created_by must be supplied. The factory's own default is
        // User::factory()->instructor(), so omitting it quietly creates a new
        // instructor account for every quiz.
        'created_by' => $instructor->id,
        'title' => $title.' check',
        'status' => $quizPublished,
    ]);

    $question = new QuizQuestion;
    $question->forceFill([
        'quiz_id' => $quiz->id,
        'prompt' => 'Which statement best describes the topic?',
        'position' => 1,
        'points' => 1,
        'question_type' => $multipleChoice,
    ]);
    $question->save();

    foreach (['First option', 'Second option', 'Third option', 'Fourth option'] as $position => $text) {
        $option = new QuizOption;
        $option->forceFill([
            'question_id' => $question->id,
            'option_text' => $text,
            'position' => $position + 1,
            'is_correct' => $position === 0,
        ]);
        $option->save();
    }

    /*
     | The factory state, not a `status` override.
     |
     | Passing `status` as an attribute replaces the whole state, so the
     | `activated_at` that `active()` sets alongside it never happened. That
     | produced an enrollment the application itself can never create: live,
     | with no activation date. Every panel that reads real dates then treated
     | this learner as having never started, so the Student dashboard showed 21
     | completed lessons above an activity feed that said nothing had happened.
     |
     | The correlated columns are the reason the state exists. Writing the
     | status by hand is how they came apart.
     */
    $enrollment = Enrollment::factory()->active()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    foreach (array_slice($lessons, 0, $completedCount) as $offset => $lesson) {
        $progress = new LessonProgress;
        $progress->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => $lessonCompleted,
            'started_at' => now()->subDays(6 - $offset),
            'completed_at' => now()->subDays(5 - $offset),
            'last_viewed_at' => now()->subDay(),
        ]);
        $progress->save();
    }

    if ($index === 0) {
        // One finished course, so the completed state and a certificate are real
        // records rather than an imagined layout.
        $enrollment->forceFill([
            'status' => $enrollmentCompleted,
            'activated_at' => now()->subDays(30),
            'completed_at' => now()->subDays(2),
        ])->save();

        $payment = new Payment;
        $payment->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount_minor' => 0,
            'currency' => 'PHP',
            'status' => $paymentPaid,
            'provider' => 'paymongo',
            'idempotency_key' => 'enrollment-'.$enrollment->id,
            'provider_checkout_id' => 'cs_DEMO_'.$course->id,
            'provider_payment_id' => 'pay_DEMO_'.$course->id,
            'paid_at' => now()->subDays(30),
        ]);
        $payment->save();

        $certificate = new Certificate;
        $certificate->forceFill([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'certificate_code' => 'ITH-DEMO-'.str_pad((string) $course->id, 4, '0', STR_PAD_LEFT),
            'student_name_snapshot' => $student->name,
            'course_title_snapshot' => $course->title,
            'completion_date' => now()->subDays(2)->toDateString(),
            'status' => $certificateIssued,
        ]);
        $certificate->save();
    }

    if ($index === 1 || $index === 2) {
        // One pass and one fail, so both assessment outcomes are on screen. The
        // factory states set the real graded columns, which are score_points,
        // total_points and score_percent rather than a single score column.
        $when = now()->subDays($index === 1 ? 3 : 4);

        QuizAttempt::factory()
            ->for($quiz)
            ->for($enrollment)
            ->for($student, 'student')
            ->{$index === 1 ? 'passed' : 'failed'}($index === 1 ? 85.0 : 40.0)
            ->create(['started_at' => $when, 'submitted_at' => $when]);
    }

    // A second student, so the instructor has more than one person and the
    // administrator's user list has something to page through.
    // Awaiting payment by default, which is the state that genuinely has no
    // activation date, because only a verified payment event or a free course
    // sets one.
    $secondEnrollment = Enrollment::factory()->create([
        'student_id' => $otherStudent->id,
        'course_id' => $course->id,
        'status' => $pendingPayment,
    ]);

    // Every third row is a learner who has actually started. The status and the
    // date are written together on purpose, for the same reason as above.
    if ($index % 3 !== 0) {
        $secondEnrollment->forceFill([
            'status' => $enrollmentActive,
            'activated_at' => now(),
        ]);
        $secondEnrollment->save();
    }
}

file_put_contents($markFile, json_encode($marks, JSON_PRETTY_PRINT));

fwrite(STDERR, "\n  demo data created\n");

foreach (['users', 'courses', 'modules', 'lessons', 'enrollments', 'lesson_progress', 'quizzes', 'quiz_attempts', 'payments', 'certificates'] as $table) {
    fwrite(STDERR, sprintf("    %-16s %d\n", $table, DB::table($table)->count()));
}

fwrite(STDERR, "\n  demo student:    {$student->email}\n");
fwrite(STDERR, "  demo instructor: {$instructor->email}\n");
fwrite(STDERR, "\n  remove it with: php tools/seed-dashboard-demo.php --clean\n");
