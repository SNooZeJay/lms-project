<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Removes the accounts the audit and probe tools leave behind.
 *
 * The companion to `remove-the-audit-fixtures.php`, which handles courses. The
 * probes make a student, a second student and an instructor in order to ask
 * questions about ownership, and the first version of them left those accounts
 * on the demonstration database: seventy three users where a demonstration
 * expects a handful, and the account menu listing every one of them.
 *
 * An account is only removed when it is provably not a demonstration account:
 * it has no enrollment, no course, no attempt, no certificate, no conversation,
 * no announcement and no activity log. Anything that appears anywhere in the
 * application's own data is left alone, whatever its name.
 *
 * The column names were read from the live schema rather than guessed. Three were
 * wrong on the first attempt: a conversation has `requester_id` and not
 * `created_by`, an announcement has `author_id`, and an activity log has
 * `actor_id`. A query against a column that does not exist is a fatal, and a
 * fatal in the middle of a cleanup leaves the tool having done nothing without
 * saying so.
 *
 * Usage: php tools/remove-the-audit-accounts.php [--dry]
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dry = in_array('--dry', $argv, true);

/*
 | Every table that mentions a user, and the column that mentions them.
 |
 | This is the list that has to be right. A user with an enrollment is a real
 | learner, a user with a course is a real instructor, and a user with an
 | activity log has been through a flow. Anything absent from this list would be
 | deleted while still being referenced, which is a worse outcome than leaving a
 | stray account in place.
 */
$where = [
    'enrollments' => 'student_id',
    'courses' => 'instructor_id',
    'quiz_attempts' => 'student_id',
    'certificates' => 'student_id',
    'conversations' => 'requester_id',
    'announcements' => 'author_id',
    'activity_logs' => 'actor_id',
    'quizzes' => 'created_by',
    'course_requirements' => 'updated_by',
    'conversation_participants' => 'user_id',
    'conversation_messages' => 'sender_id',
    'learning_materials' => 'uploaded_by',
];

$referenced = [];

foreach (DB::table('users')->pluck('id') as $id) {
    foreach ($where as $table => $column) {
        try {
            if (DB::table($table)->where($column, $id)->exists()) {
                $referenced[$id] = true;

                break;
            }
        } catch (Throwable $e) {
            // A table that does not have that column cannot be referencing it.
            continue;
        }
    }
}

$all = DB::table('users')->pluck('id')->all();
$doomed = array_values(array_diff($all, array_keys($referenced)));

echo "\n  ".count($all).' accounts, '.count($referenced)." of them referenced by something.\n";
echo '  '.count($doomed)." are unreferenced and removable.\n";

if ($doomed !== []) {
    echo "\n  Removable:\n";

    foreach (DB::table('users')->whereIn('id', $doomed)->get(['id', 'name', 'email']) as $user) {
        echo "    {$user->id}  {$user->name}  <{$user->email}>\n";
    }
}

if ($dry) {
    echo "\n  Nothing was removed. Re-run without --dry.\n\n";

    exit(0);
}

/*
 | The profile goes first, because the user carries it and the schema restricts
 | the delete rather than cascading it.
 */
DB::transaction(function () use ($doomed): void {
    foreach ($doomed as $id) {
        DB::table('profiles')->where('user_id', $id)->delete();
    }

    DB::table('users')->whereIn('id', $doomed)->delete();
});

echo "\n  Accounts now: ".DB::table('users')->count()."\n\n";

exit(0);
