<?php

/**
 * Repairs enrollments that are live but carry no activation date.
 *
 * An enrollment in `active` or `completed` always has an `activated_at`. The
 * real flows set them together and cannot produce a row without one:
 *
 *   - `EnrollStudent` sets `now()` for a free course, and leaves a paid one at
 *     `pending_payment`, which correctly has no date.
 *   - `ProcessPayMongoEvent` sets it on the verified event that activates.
 *   - `EnrollmentFactory::active()` and `::completed()` set it in the same state
 *     that sets the status.
 *
 * A row with a status but no date can only come from somewhere that wrote the
 * status on its own. `tools/seed-dashboard-demo.php` did exactly that, by
 * passing `status` as a factory attribute override, which replaces the state
 * and so discards the date that came with it.
 *
 * Why it matters, and not just as untidiness: the Student dashboard, the
 * Continue Learning panel and the activity feed all read real dates. An
 * enrollment in this state reads as "has not started", so the dashboard showed
 * 21 completed lessons above a feed saying nothing had happened.
 *
 * This tool only ever fills in a date that is missing. It never changes a
 * status, never touches an enrollment that already has a date, and never
 * invents a time: the date is taken from the learner's own earliest lesson
 * progress, and falls back to the row's own creation time when there is no
 * progress yet. Running it twice changes nothing the second time.
 *
 * Usage:  php tools/repair-enrollment-activations.php [--dry]
 */

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dry = in_array('--dry', $argv, true);

echo PHP_EOL;
echo '  Repairing live enrollments with no activation date'.($dry ? '  (dry run, nothing written)' : '').PHP_EOL;
echo PHP_EOL;

$live = [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value];

$broken = Enrollment::query()
    ->whereIn('status', $live)
    ->whereNull('activated_at')
    ->orderBy('id')
    ->get();

if ($broken->isEmpty()) {
    echo '  nothing to repair: every live enrollment has an activation date'.PHP_EOL;
    echo PHP_EOL;

    return;
}

$fixed = 0;

foreach ($broken as $enrollment) {
    /*
     * The learner's own earliest evidence of having started. When a learner has
     * progress, that is a truer answer than the creation time, because it is
     * when they actually turned up rather than when the row appeared.
     */
    $firstLesson = DB::table('lesson_progress')
        ->where('enrollment_id', $enrollment->id)
        ->whereNotNull('started_at')
        ->orderBy('started_at')
        ->value('started_at');

    $when = $firstLesson ?? $enrollment->created_at;

    if ($when === null) {
        // Nothing to go on. Left alone and reported, because guessing a date
        // would put a fabricated timestamp into a column that everything else
        // on the page is derived from.
        printf("    enrollment %-5s %-10s  SKIPPED: no creation time and no progress\n",
            $enrollment->id,
            $enrollment->status->value
        );

        continue;
    }

    printf("    enrollment %-5s %-10s  activated_at <- %s%s\n",
        $enrollment->id,
        $enrollment->status->value,
        $when,
        $firstLesson === null ? '   (no progress yet, so the creation time)' : '   (earliest lesson progress)'
    );

    if (! $dry) {
        $enrollment->forceFill(['activated_at' => $when])->save();
    }

    $fixed++;
}

printf(
    "\n  %s %d %s\n\n",
    $dry ? 'would repair' : 'repaired',
    $fixed,
    $fixed === 1 ? 'enrollment' : 'enrollments'
);
