<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

/*
 | Removes the accounts the probes left behind.
 |
 | The probes register, verify and then enrol, so an account one of them made can
 | have rows pointing at it. The database refuses to delete a user that anything
 | references, which is the right refusal and is why this tool clears the
 | references first rather than working around the constraint.
 |
 | The tables that reference a user are read from the schema rather than written
 | out here. A list in this file would be a list that goes stale the next time a
 | table is added, and the failure would be a constraint violation on a table
 | nobody remembered to add. Asking the database means a new dependent table is
 | handled without this file being touched.
 |
 | How an account is recognised as a probe's decides how much of that is safe to
 | do, and the two cases are treated differently. A generated name is certain: the
 | probes write those names and nothing else does, so an account carrying one is
 | certainly a probe's, and clearing its rows cannot touch a real person. An
 | address under a domain that cannot receive mail is not certain, and such an
 | account is only touched while it is unverified and completely unused.
 |
 | The removal is one transaction per account. A partial cleanup that left rows
 | behind would be worse than none, because the account would look removed and
 | still hold the records a probe made.
 */

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$probeNames = ['Demo Fresh Student', 'Cannot Verify Demo', 'Verification Probe', 'Probe', 'First Run Student', 'Confirm Check'];

/**
 * Every table with a foreign key pointing at users.id.
 *
 * @return list<array{table: string, column: string}>
 */
function tablesReferencingUsers(): array
{
    $database = (string) DB::selectOne('select database() as name')->name;

    return array_map(
        fn ($row): array => ['table' => (string) $row->TABLE_NAME, 'column' => (string) $row->COLUMN_NAME],
        DB::select(
            'select TABLE_NAME, COLUMN_NAME from information_schema.KEY_COLUMN_USAGE
             where REFERENCED_TABLE_SCHEMA = ? and REFERENCED_TABLE_NAME = ?
               and REFERENCED_TABLE_NAME <> TABLE_NAME
             order by TABLE_NAME',
            [$database, 'users'],
        )
    );
}

$users = User::query()
    ->where(function ($query) use ($probeNames): void {
        $query->where('email', 'like', '%@example.com')
            ->orWhereIn('name', $probeNames);
    })
    ->with('profile')
    ->get();

echo PHP_EOL.'  the accounts the probes left behind: '.$users->count().PHP_EOL;

if ($users->isEmpty()) {
    echo PHP_EOL.'  nothing to do.'.PHP_EOL.PHP_EOL;

    return;
}

$references = tablesReferencingUsers();
echo '  '.count($references).' table(s) reference a user, read from the schema:'.PHP_EOL;
echo '    '.implode(', ', array_column($references, 'table')).PHP_EOL.PHP_EOL;

$removed = 0;
$kept = 0;

foreach ($users as $user) {
    $enrolled = DB::table('enrollments')->where('student_id', $user->id)->count();
    $progress = DB::table('lesson_progress')->where('student_id', $user->id)->count();
    $attempts = DB::table('quiz_attempts')->where('student_id', $user->id)->count();
    $verified = $user->hasVerifiedEmail();
    $used = $enrolled > 0 || $progress > 0 || $attempts > 0 || $verified;

    $nameIsOurs = in_array($user->name, $probeNames, true);
    $addressCannotReceive = str_ends_with($user->email, '@example.com');

    if (! $nameIsOurs && $addressCannotReceive && $used) {
        $kept++;
        echo '    #'.$user->id.'  kept, an undeliverable address that has been used, so it might be a real account'.PHP_EOL;

        continue;
    }

    // Ordering matters where one reference points at another, so a table is
    // cleared only once nothing references it any more. The pass repeats until
    // it stops making progress, and gives up rather than looping forever.
    $cleared = DB::transaction(function () use ($user, $references): int {
        $rows = 0;
        $pending = $references;

        for ($pass = 0; $pass < 12 && $pending !== []; $pass++) {
            $deferred = [];

            foreach ($pending as $reference) {
                try {
                    $deleted = DB::table($reference['table'])->where($reference['column'], $user->id)->delete();
                    $rows += $deleted;
                } catch (Throwable) {
                    // Something still points at these rows, so try again next pass.
                    $deferred[] = $reference;
                }
            }

            if (count($deferred) === count($pending)) {
                // No progress. Rather than spin, leave the account alone.
                throw new RuntimeException('could not clear every table that references this user');
            }

            $pending = $deferred;
        }

        $pending === [] || throw new RuntimeException('gave up clearing the tables that reference this user');

        $user->delete();

        return $rows;
    });

    $removed++;
    echo '    #'.$user->id.'  removed with '.$cleared.' referencing row(s)'
        .($nameIsOurs ? ', a name only a probe writes' : '')
        .' ('.$user->email.')'.PHP_EOL;
}

echo PHP_EOL.'  removed '.$removed.', kept '.$kept.'.'.PHP_EOL;

echo PHP_EOL.'  every account now:'.PHP_EOL;
foreach (User::query()->with('profile')->orderBy('id')->get() as $user) {
    printf(
        '    #%-4d %-42s %-13s %-9s %d enrolled%s',
        $user->id,
        $user->email,
        $user->profile?->role->value ?? '(none)',
        $user->hasVerifiedEmail() ? 'verified' : 'UNVERIFIED',
        DB::table('enrollments')->where('student_id', $user->id)->count(),
        PHP_EOL
    );
}

echo PHP_EOL;
