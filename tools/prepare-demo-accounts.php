<?php

/*
 | Applies a demonstration account list, and retires the accounts it supersedes.
 |
 | WHY THE PASSWORDS ARE NOT IN THIS FILE
 |
 | They are read from a JSON file whose path is given on the command line, and
 | that file lives outside the repository. A credential written into a tool is a
 | credential in the history forever, and a history is the one place a secret is
 | hardest to take back. The tool is committed; the file is not.
 |
 | The tool also never prints a password, not even to say it set one. What it
 | reports is whether a password was applied, never what it was.
 |
 | WHY IT CHECKS EVERYTHING BEFORE IT WRITES ANYTHING
 |
 | A list is applied in two passes: every entry is checked, and only if every one
 | of them passes is anything written. The first version checked and wrote in the
 | same breath, so a list whose fifth entry asked for the wrong role created the
 | first four accounts and then stopped. Half a demonstration applied is worse than
 | none: the accounts exist, the passwords are set, and the person using them is
 | not the person they were prepared for.
 |
 | WHAT IT REFUSES, AND WHY
 |
 | An account that owns courses, authored a quiz, or wrote an announcement is not
 | retired, whatever the list says. Those are what a demonstration is built out of,
 | and a seeder that quietly deleted the instructor who owns the catalog would
 | empty it. The refusal names the account and what it holds.
 |
 | A role is never rewritten from a data file. An address that already belongs to
 | an account with a different role is reported, because turning a Student into
 | an Administrator is a decision somebody has to make on purpose.
 |
 | WHY NEW ACCOUNTS ARE MARKED VERIFIED
 |
 | The plan requires verification before any role area, and the verification link
 | is emailed. Preparing a demonstration cannot depend on five people being awake
 | to click five links, so the accounts are marked verified here. That is the
 | documented demonstration path and it is not a secret: BootstrapOwner does the
 | same for the owner account.
 |
 | IT IS SAFE TO RUN TWICE
 |
 | Every write is a comparison against what is stored, and a run that changes
 | nothing says so. The retire step only removes what it finds still there.
 *
 | Usage: php tools/prepare-demo-accounts.php <path to the account file, outside this repo>
 */

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$roles = ['administrator', 'instructor', 'student'];

$say = static function (string $line = ''): void {
    echo '  '.$line.PHP_EOL;
};

// --- the account file, and the rules about where it may live ----------------
$specPath = $argv[1] ?? '';

if ($specPath === '' || ! is_file($specPath)) {
    $say();
    $say('no account file was given, so nothing was changed.');
    $say('Usage: php tools/prepare-demo-accounts.php <path outside this repository>');
    $say();

    exit(1);
}

$realSpec = realpath($specPath);
$realRoot = realpath($root);

// A credential file that lives beside the tool is one `git add .` from the history.
if ($realSpec === false || str_starts_with($realSpec, $realRoot.DIRECTORY_SEPARATOR)) {
    $say();
    $say('the account file is inside the repository, so it was refused.');
    $say('Put it somewhere else. A password written into a file beside this tool is');
    $say('one `git add .` away from the history, and a history is the one place a');
    $say('secret is hardest to take back.');
    $say();

    exit(1);
}

/*
 | Read, with the byte order mark taken off first.
 |
 | A file saved by an editor that writes a mark is still a file somebody meant to
 | be read, and refusing it with "could not be read" is a confusing answer to a
 | real cause. The mark is three bytes that mean nothing to the writer and break
 | the first character of the JSON.
 */
$raw = (string) file_get_contents($realSpec);

if (str_starts_with($raw, "\xEF\xBB\xBF")) {
    $raw = substr($raw, 3);
}

$spec = json_decode($raw, true);

if (! is_array($spec) || ! is_array($spec['accounts'] ?? null)) {
    $say();
    $say('the account file could not be read, so nothing was changed.');
    $say();

    exit(1);
}

$say();
$say('=== reading the account list ===');
$say(count($spec['accounts']).' account(s) to apply, '.count($spec['supersede'] ?? []).' to retire.');
$say('No password is printed by this tool at any point.');
$say();

// --- pass one: check every entry, write nothing ----------------------------
$problems = [];
$plan = [];

/** What a retirement would destroy, if it were allowed to happen. */
$holds = static function (User $user): array {
    $holds = [];

    if (Course::where('instructor_id', $user->id)->exists()) {
        $holds[] = 'courses owned';
    }

    if (DB::table('quizzes')->where('created_by', $user->id)->exists()) {
        $holds[] = 'quizzes authored';
    }

    if (DB::table('announcements')->where('author_id', $user->id)->exists()) {
        $holds[] = 'announcements written';
    }

    if (DB::table('certificates')->where('student_id', $user->id)->exists()) {
        $holds[] = 'certificates held';
    }

    return $holds;
};

foreach ($spec['accounts'] as $position => $entry) {
    $email = strtolower(trim((string) ($entry['email'] ?? '')));
    $name = trim((string) ($entry['name'] ?? ''));
    $role = trim((string) ($entry['role'] ?? ''));
    $password = (string) ($entry['password'] ?? '');
    $where = 'entry '.($position + 1);

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $problems[] = $where.': ['.$email.'] is not an address';

        continue;
    }

    if (! in_array($role, $roles, true)) {
        $problems[] = $email.': the role ['.$role.'] is not one this application has';

        continue;
    }

    if ($name === '' || mb_strlen($name) > 255) {
        $problems[] = $email.': the name is empty or too long to store';

        continue;
    }

    if ($password !== '') {
        $check = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::min(12)]],
        );

        if ($check->fails()) {
            $problems[] = $email.': the password does not satisfy the project rules ('.implode(' ', $check->errors()->all()).')';

            continue;
        }
    }

    $existing = User::query()->where('email', $email)->first();

    if ($existing !== null && $existing->profile?->role->value !== $role) {
        $problems[] = $email.': it is a '.$existing->profile->role->value.' and the list says '.$role
            .'. A role is not corrected from a data file';

        continue;
    }

    $plan[] = compact('email', 'name', 'role', 'password', 'existing');
}

foreach ($spec['supersede'] ?? [] as $entry) {
    $email = strtolower(trim((string) ($entry['email'] ?? '')));
    $user = User::query()->where('email', $email)->first();

    if ($user === null) {
        continue;
    }

    $held = $holds($user);

    if ($held !== []) {
        $problems[] = $email.': it holds '.implode(', ', $held).', and retiring it would empty the demonstration';

        continue;
    }
}

if ($problems !== []) {
    $say('=== these stop the whole run, and nothing was written ===');
    $say();

    foreach ($problems as $problem) {
        $say('  '.$problem);
    }

    $say();
    $say('Move the data across first, or take the entry off the list and say what');
    $say('should happen to it instead. A list of names is not a good enough reason');
    $say('to destroy what a demonstration is built out of.');
    $say();

    exit(1);
}

// --- pass two: apply --------------------------------------------------------
$created = 0;
$updated = 0;
$alreadyCorrect = 0;

foreach ($plan as $item) {
    if ($item['existing'] === null) {
        /*
         | The role is set by assignment, not by passing it to create().
         |
         | Profile has bio as its only fillable field, because a role is never
         | something a request or a fixture is allowed to hand over. Passing role
         | into create() is therefore dropped without a word, the column takes its
         | database default, and every account in the list is created as a
         | Student. The rehearsal caught this: five accounts reported as an
         | administrator, an instructor and three students, and every one of them
         | was a student, which on the real database would have left a
         | demonstration with nobody able to administer or teach anything.
         |
         | This is the same way BootstrapOwner does it, and the reason is the same.
         */
        $user = DB::transaction(function () use ($item): User {
            $user = User::create([
                'name' => $item['name'],
                'email' => $item['email'],
                'password' => Hash::make($item['password'] !== '' ? $item['password'] : Str::random(40)),
            ]);

            $profile = $user->profile()->create();
            $profile->role = $item['role'];
            $profile->account_status = 'active';
            $profile->must_change_password = false;
            $profile->save();

            // Verified rather than left waiting on an inbox nobody is watching.
            $user->forceFill(['email_verified_at' => now()])->save();

            return $user->fresh();
        });

        $actual = $user->profile?->role->value;

        if ($actual !== $item['role']) {
            $say('NOT APPLIED  '.$item['email'].'  wanted '.$item['role'].' and the stored role is '.$actual);
            $say('              The account exists and must be looked at by hand.');

            continue;
        }

        $created++;
        $say('created  '.$item['email'].'  as '.$actual
            .($item['password'] !== '' ? ', with the password applied' : ', with a random password because none was given'));

        continue;
    }

    $user = $item['existing'];
    $profile = $user->profile;
    $changes = [];

    if ($user->name !== $item['name']) {
        $changes[] = 'name';
    }

    if ($item['password'] !== '' && ! Hash::check($item['password'], $user->password)) {
        $changes[] = 'password';
    }

    if (! $user->hasVerifiedEmail()) {
        $changes[] = 'verified';
    }

    if ($changes === []) {
        $alreadyCorrect++;
        $say('already correct  '.$item['email']);

        continue;
    }

    $updates = [];

    if (in_array('name', $changes, true)) {
        $updates['name'] = $item['name'];
    }

    if (in_array('password', $changes, true)) {
        $updates['password'] = Hash::make($item['password']);
    }

    if (in_array('verified', $changes, true)) {
        $updates['email_verified_at'] = now();
    }

    DB::transaction(function () use ($user, $profile, $updates): void {
        $user->forceFill($updates)->save();

        // Neither may block a demonstration: a suspended account cannot sign in,
        // and one forced to change its password cannot reach a dashboard.
        if ($profile->account_status->value !== 'active') {
            $profile->forceFill(['account_status' => 'active'])->save();
        }

        if ($profile->must_change_password) {
            $profile->forceFill(['must_change_password' => false])->save();
        }
    });

    $updated++;
    $say('updated  '.$item['email'].'  ('.implode(', ', $changes).')');
}

// --- retire the superseded accounts ----------------------------------------
$references = DB::select(
    'select TABLE_NAME as t, COLUMN_NAME as c from information_schema.KEY_COLUMN_USAGE
     where REFERENCED_TABLE_SCHEMA = database() and REFERENCED_TABLE_NAME = ?
       and REFERENCED_TABLE_NAME <> TABLE_NAME order by TABLE_NAME',
    ['users'],
);

$retired = 0;

foreach ($spec['supersede'] ?? [] as $entry) {
    $email = strtolower(trim((string) ($entry['email'] ?? '')));
    $moveTo = strtolower(trim((string) ($entry['move_enrollments_to'] ?? '')));
    $user = User::query()->where('email', $email)->first();

    if ($user === null) {
        $say('already gone  '.$email);

        continue;
    }

    if ($moveTo !== '' && User::query()->where('email', $moveTo)->doesntExist()) {
        $say('left alone  '.$email.': the account to move its records to, '.$moveTo.', does not exist');
        $say('            Run this again once that account is in place');

        continue;
    }

    // The database refuses to delete a user anything points at, which is the right
    // refusal, so what points at it is read from the schema and cleared here.
    DB::transaction(function () use ($user, $moveTo, $references): void {
        if ($moveTo !== '') {
            $target = User::query()->where('email', $moveTo)->first();

            $moved = 0;
            foreach (['enrollments', 'lesson_progress', 'quiz_attempts'] as $table) {
                $moved += DB::table($table)->where('student_id', $user->id)->update(['student_id' => $target->id]);
            }

            if ($moved > 0) {
                echo '    moved '.$moved." record(s) of learning from {$user->email} to {$moveTo}".PHP_EOL;
            }
        }

        $pending = $references;

        for ($pass = 0; $pass < 12 && $pending !== []; $pass++) {
            $deferred = [];

            foreach ($pending as $reference) {
                try {
                    DB::table($reference->t)->where($reference->c, $user->id)->delete();
                } catch (Throwable) {
                    $deferred[] = $reference;
                }
            }

            if (count($deferred) === count($pending)) {
                throw new RuntimeException('could not clear every table that references this account');
            }

            $pending = $deferred;
        }

        $user->delete();
    });

    $retired++;
    $say('retired  '.$email);
}

$say();
$say('=== result ===');
$say(sprintf('%d created, %d updated, %d already correct, %d retired', $created, $updated, $alreadyCorrect, $retired));
$say();
