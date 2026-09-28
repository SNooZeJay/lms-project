<?php

/*
 | Repairs the rows an audit of the live database found to be impossible.
 |
 | WHAT THIS FIXES, AND WHY EACH ONE NEEDS A REPAIR RATHER THAN A MIGRATION
 |
 | 1. Two lesson progress rows say completed with no date beside it.
 |    MarkLessonComplete left completed_at out of the columns an upsert may
 |    overwrite, so a lesson that had been opened first took the update path and
 |    never had the date written. The action now adds the column to the overwrite
 |    list only when the row is moving onto completed, and a test pins both halves:
 |    the date is written on the visit-first path, and a second press does not move
 |    it. This fills the two rows the old action left behind, because they are real
 |    records of a learner having finished something and a null date makes every
 |    panel that reads one treat the lesson as unfinished.
 |
 |    The date comes from last_viewed_at, which is the moment the learner was last
 |    on the lesson and is after started_at in both rows. It is the best evidence
 |    available, not the true moment, and a repair that invented a plausible time
 |    from created_at would be a worse lie. The seeded rows for the finished
 |    student carry a real completed_at and are not touched.
 |
 | 2. Two profile rows belong to accounts that do not exist.
 |    profiles.user_id is a foreign key with a cascade, and it is enforced, so
 |    these cannot have been written while it was in place. They came from a load
 |    or a seed that turned the checks off, which is what a data load does. Nothing
 |    reads them, because every question about a person starts from the user, so
 |    they are inert rather than dangerous, and they are removed because a role with
 |    no account behind it is a row that means nothing.
 |
 | 3. Three notices point at an announcement that was withdrawn.
 |    notifications.subject_type and subject_id are a reference no foreign key can
 |    hold, because the same columns point at a quiz, a certificate and a
 |    conversation. Nothing cascades. Withdrawing an announcement now takes its
 |    notices with it in the same transaction, and a test pins it, so these three
 |    are the last ones and they are removed here.
 |
 | IT NEVER OVERWRITES A GOOD ROW
 |
 | Every repair is scoped to a row that is currently wrong, and the progress repair
 | additionally refuses to touch a row that already has a date. Running it twice
 | changes nothing the second time, which is the only way to be able to run a
 | repair against a database somebody is using.
 |
 | Usage: php tools/repair-impossible-rows.php [--apply]
 |
 | Without --apply it only reports. That is the default, because a repair whose
 | first run writes is a repair nobody will run against a copy first.
 */

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);

$say = static function (string $line = ''): void {
    echo '  '.$line.PHP_EOL;
};

/* ------------------------------------------- 1. a finished lesson with no date */

$undated = DB::table('lesson_progress')
    ->where('status', 'completed')
    ->whereNull('completed_at')
    ->orderBy('id')
    ->get();

$say();
$say('=== 1. lesson progress rows marked finished with no date ===');
$say('  '.$undated->count().' row(s)');

foreach ($undated as $row) {
    $who = DB::table('users')->where('id', $row->student_id)->value('email');
    $lesson = DB::table('lessons')->where('id', $row->lesson_id)->value('title');

    /*
     * last_viewed_at is when the learner was last on the lesson, which is the
     * closest thing to when they finished that the row still holds. started_at is
     * the fallback, and it is a worse answer, so it is only used if there is
     * genuinely nothing else. Whichever is used, it has to be at or after
     * started_at: a completion before the start would be a row that says a learner
     * finished something before they opened it.
     */
    $source = $row->last_viewed_at ?? $row->updated_at ?? $row->started_at;

    $say();
    $say(sprintf('    #%d  %s', $row->id, (string) $who));
    $say(sprintf('      lesson            %s', (string) $lesson));
    $say(sprintf('      started_at        %s', (string) $row->started_at));
    $say(sprintf('      last_viewed_at    %s', (string) $row->last_viewed_at));
    $say(sprintf('      would take        %s   from last_viewed_at', (string) $source));

    if ($source === null) {
        say('      NOT REPAIRABLE    the row holds no time at all, so there is nothing honest to write');

        continue;
    }

    if ($row->started_at !== null && strtotime((string) $source) < strtotime((string) $row->started_at)) {
        say('      NOT REPAIRABLE    the only time available is before the lesson was started');

        continue;
    }
}

/* -------------------------------------- 2. a role belonging to no account */

$orphanProfiles = DB::table('profiles as p')
    ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
    ->whereNull('u.id')
    ->orderBy('p.user_id')
    ->get(['p.user_id', 'p.role', 'p.account_status', 'p.created_at']);

$say();
$say('=== 2. profile rows with no account behind them ===');
$say('  '.$orphanProfiles->count().' row(s)');

foreach ($orphanProfiles as $row) {
    $say(sprintf('    user %-6d role %-14s status %-10s created %s', $row->user_id, (string) $row->role, (string) $row->account_status, (string) $row->created_at));
}

/* ---------------------------------- 3. notices about an announcement that is gone */

$announcementIds = DB::table('announcements')->pluck('id')->all();

$orphanNotices = DB::table('notifications as n')
    ->leftJoin('users as u', 'u.id', '=', 'n.user_id')
    ->where('n.subject_type', 'announcement')
    ->whereNotIn('n.subject_id', $announcementIds === [] ? [0] : $announcementIds)
    ->orderBy('n.id')
    ->get(['n.id', 'n.user_id', 'n.subject_id', 'n.type', 'n.title', 'n.read_at']);

$say();
$say('=== 3. notices pointing at an announcement that no longer exists ===');
$say('  '.$orphanNotices->count().' row(s)');

foreach ($orphanNotices as $row) {
    $who = DB::table('users')->where('id', $row->user_id)->value('email');
    $say(sprintf(
        '    #%d  %-40s about announcement #%d  %s',
        $row->id,
        (string) $who,
        $row->subject_id,
        $row->read_at === null ? 'unread' : 'already read'
    ));
}

$total = $undated->count() + $orphanProfiles->count() + $orphanNotices->count();

$say();
$say('=== '.$total.' row(s) in total ===');

if (! $apply) {
    $say();
    $say('Nothing was changed. Add --apply to write the repairs above.');

    return;
}

$say();

$written = DB::transaction(function () use ($undated, $orphanProfiles, $orphanNotices): array {
    $dates = 0;

    foreach ($undated as $row) {
        $source = $row->last_viewed_at ?? $row->updated_at ?? $row->started_at;

        if ($source === null) {
            continue;
        }

        if ($row->started_at !== null && strtotime((string) $source) < strtotime((string) $row->started_at)) {
            continue;
        }

        // Scoped to the row as it is now, so a row that gained a date between the
        // report and the write is not overwritten with a worse one.
        $dates += DB::table('lesson_progress')
            ->where('id', $row->id)
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => $source, 'updated_at' => $source]);
    }

    $profiles = 0;
    foreach ($orphanProfiles as $row) {
        $profiles += DB::table('profiles')->where('user_id', $row->user_id)->delete();
    }

    $notices = 0;
    foreach ($orphanNotices as $row) {
        $notices += DB::table('notifications')
            ->where('id', $row->id)
            ->where('subject_type', 'announcement')
            ->delete();
    }

    return [$dates, $profiles, $notices];
});

$say(sprintf('  %d completion date(s) filled in.', $written[0]));
$say(sprintf('  %d orphan profile row(s) removed.', $written[1]));
$say(sprintf('  %d notice(s) about a withdrawn announcement removed.', $written[2]));

$say();
$say('=== what is left ===');

$say(sprintf(
    '  lesson progress marked finished with no date : %d',
    DB::table('lesson_progress')->where('status', 'completed')->whereNull('completed_at')->count()
));
$say(sprintf(
    '  profile rows with no account               : %d',
    DB::table('profiles as p')->leftJoin('users as u', 'u.id', '=', 'p.user_id')->whereNull('u.id')->count()
));
$say(sprintf(
    '  notices pointing at a missing announcement  : %d',
    DB::table('notifications as n')
        ->leftJoin('announcements as a', 'a.id', '=', 'n.subject_id')
        ->where('n.subject_type', 'announcement')
        ->whereNull('a.id')
        ->count()
));
$say();
