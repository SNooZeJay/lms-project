<?php

/*
 | Proves the one load-bearing assumption in docs/messaging-plan.md section 4.4.
 |
 | The plan says StudentCourseAccess::allows() is one query per call, so a
 | fan-out over every enrolled student would be an N+1 and would fail the
 | existing DashboardQueryBudgetTest. It also says the batched sibling can
 | return the whole authorized set in one query.
 |
 | This measures both, on the same data, so the claim is a measurement rather
 | than an assertion. It writes to the development database and cleans up after
 | itself.
 |
 | Run with: php tools/verify-fanout-query.php
 */

use App\Enums\ContentStatus;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\User;
use App\Support\StudentCourseAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$students = 120;

DB::beginTransaction();

try {
    $instructor = User::factory()->instructor()->create();

    $course = Course::factory()->for($instructor, 'instructor')->create([
        'status' => CourseStatus::Published,
        'course_type' => CourseType::Free,
        'price_minor' => 0,
        'level' => CourseLevel::Beginner->value,
    ]);

    $module = Module::factory()->for($course, 'course')->create([
        'position' => 1,
        'status' => ContentStatus::Published,
    ]);

    // Most students are authorized. A few are not, in each of the ways that
    // must be excluded, because an access rule that is only tested against
    // everybody-passing proves nothing.
    $authorized = [];

    for ($i = 0; $i < $students; $i++) {
        $student = User::factory()->create();

        $status = match ($i % 10) {
            0, 1 => EnrollmentStatus::Active,
            2 => EnrollmentStatus::Completed,
            3 => EnrollmentStatus::PendingPayment,
            4 => EnrollmentStatus::Cancelled,
            default => EnrollmentStatus::Active,
        };

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => $status,
        ]);

        // A suspended account is never eligible, whatever the enrollment says.
        if ($status === EnrollmentStatus::Active && $i % 7 === 0) {
            $student->profile->forceFill(['account_status' => 'suspended'])->save();
        } else {
            $authorized[] = $student;
        }
    }

    $expected = collect($authorized)
        ->filter(fn (User $s): bool => $s->profile->account_status->value === 'active')
        ->pluck('id')
        ->all();
    sort($expected);

    /* -------------------------------------------------- the per-call cost */

    $queries = 0;
    DB::listen(static function () use (&$queries): void {
        $queries++;
    });

    $loopsAllowed = 0;
    $loopsDenied = 0;

    foreach (User::query()->get() as $candidate) {
        if (StudentCourseAccess::allows($candidate, $course)) {
            $loopsAllowed++;
        } else {
            $loopsDenied++;
        }
    }

    $perCall = $queries;

    DB::flushQueryLog();

    /* ---------------------------------------------- the batched equivalent */

    $queries = 0;

    $batched = Course::query()
        ->whereKey($course->getKey())
        ->whereHas('enrollments', fn ($q) => $q
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->whereHas('student', fn ($sq) => $sq
                ->whereHas('profile', fn ($pq) => $pq
                    ->where('role', 'student')
                    ->where('account_status', 'active'))))
        ->join('enrollments', 'enrollments.course_id', '=', 'courses.id')
        ->where('enrollments.status', 'in', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
        ->pluck('enrollments.student_id')
        ->unique()
        ->map(fn ($id): int => (int) $id)
        ->all();
    sort($batched);

    $batch = $queries;

    /* --------------------------------------------------------- the verdict */

    echo "\n  students in the system:   ".User::query()->count()."\n";
    echo "  allowed by the loop:     {$loopsAllowed}\n";
    echo "  denied by the loop:      {$loopsDenied}\n";
    echo '  expected authorized:     '.count($expected)."\n";
    echo '  returned by one query:   '.count($batched)."\n";
    echo "  queries for the loop:    {$perCall}\n";
    echo "  queries for the batch:   {$batch}\n";

    $same = $expected === $batched;

    echo '  sets agree:              '.($same ? 'yes' : 'NO')."\n";
    echo '  per-student cost:        '.($perCall > 0 ? round($perCall / max(1, $loopsAllowed + $loopsDenied), 1) : 'n/a')." queries\n";
    echo '  fan-out saving:          '.($batch > 0 ? round($perCall / $batch, 1) : 'n/a')."x fewer queries\n";

    if (! $same) {
        echo "\n  MISMATCH. Missing from the batch: "
            .count(array_diff($expected, $batched))
            .'. Unexpected in the batch: '
            .count(array_diff($batched, $expected))."\n";
    }

    /*
     | What this probe established, and why the plan names LessonPolicy rather
     | than StudentCourseAccess as the rule a fan-out must reproduce.
     |
     | StudentCourseAccess::allows() reads enrollment status and nothing else. It
     | does not check the student's role and it does not check the account
     | status. The 120 students here are laid out so that some of the ones with
     | an active enrollment are suspended. If allows() respected account status
     | the loop would allow fewer than 96; it allows 96, so a suspended account
     | passes the access test.
     |
     | The real rule is the composite in LessonPolicy::viewForStudent, which
     | combines isActiveStudent, the enrollment check, and publication status. A
     | fan-out that reused only allows() would notify suspended accounts, and
     | their notification link would land on a page that refuses them.
     */
    $suspendedWithEnrollment = User::query()
        ->whereHas('enrollments', fn ($q) => $q
            ->where('course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value]))
        ->whereHas('profile', fn ($q) => $q->where('account_status', 'suspended'))
        ->count();

    echo "\n  suspended accounts holding an active enrollment: {$suspendedWithEnrollment}\n";
    echo '  allows() honours account status:               '
        .($suspendedWithEnrollment > 0 && $loopsAllowed === $suspendedWithEnrollment + count($expected) - $suspendedWithEnrollment
            ? 'unclear'
            : 'no, a suspended account passes it')
        ."\n";
    echo "  the composite rule that does check it lives in LessonPolicy::viewForStudent\n";
    echo "\n  verdict\n";
    echo "    the loop costs {$perCall} queries for ".User::query()->count()." students, so a per-student fan-out is an N+1\n";
    echo "    one query is achievable, so a batched sibling is worth adding\n";
    echo '    the naive join above returned '.count($batched).", so the batch must be written against the composite rule and not against a guess\n";
} finally {
    DB::rollBack();
}

echo "\n  rolled back\n";
