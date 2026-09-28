<?php

/*
 | Swaps two accounts' email addresses without tripping the unique index.
 |
 | Both addresses are the one the other had, so neither can be written first:
 | updating the administrator to the address the instructor still holds collides
 | on users_email_unique. Every account is therefore parked on a placeholder
 | nobody can own, then moved to its final value.
 |
 | The addresses are passed in on the command line. Nothing is read from the
 | repository and no password appears in this file, because a script is a file
 | that gets committed by accident.
 |
 | Run: php tools/swap-account-emails.php <fromA> <toA> <fromB> <toB>
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

// A class name as a string, not an import. Imports in this project have been
// silently stripped more than once, and a script that dies before printing
// anything is a script that reports nothing.
$app->make('Illuminate'.'\\Contracts'.'\\Console'.'\\Kernel')->bootstrap();

$fromA = $argv[1] ?? null;
$toA = $argv[2] ?? null;
$fromB = $argv[3] ?? null;
$toB = $argv[4] ?? null;

if (in_array(null, [$fromA, $toA, $fromB, $toB], true)) {
    fwrite(STDERR, "usage: php tools/swap-account-emails.php <fromA> <toA> <fromB> <toB>\n");
    exit(1);
}

$userModel = 'App'.'\\Models'.'\\User';
$db = 'Illuminate'.'\\Support'.'\\Facades'.'\\DB';

$show = function ($user) {
    $role = $user->profile?->role?->value ?? 'no profile';

    return sprintf('id=%-4d %-15s %s', $user->id, $role, $user->email);
};

$find = function (string $email) use ($userModel) {
    return $userModel::query()->where('email', $email)->first();
};

$a = $find($fromA);
$b = $find($fromB);

echo "  before:\n";
echo '    '.$show($a)."\n";
echo '    '.$show($b)."\n\n";

// Refuse rather than guess. A missing account means the address is different
// from what was expected, and writing the swap anyway would be editing a
// stranger's row.
if ($a === null || $b === null) {
    fwrite(STDERR, "  one of those addresses has no account, so nothing was changed\n");
    exit(1);
}

if ($a->id === $b->id) {
    fwrite(STDERR, "  both addresses belong to the same account, so nothing was changed\n");
    exit(1);
}

if ($toA === $toB) {
    fwrite(STDERR, "  both accounts were given the same address, so nothing was changed\n");
    exit(1);
}

// Parking values that no real account can hold. If a row is left behind on one
// of these, the next run can still find it, which is what makes a failed swap
// recoverable.
$parkA = 'swap-parking-a-'.bin2hex(random_bytes(8)).'@invalid.invalid';
$parkB = 'swap-parking-b-'.bin2hex(random_bytes(8)).'@invalid.invalid';

$db::transaction(function () use ($a, $b, $toA, $toB, $parkA, $parkB) {
    $a->forceFill(['email' => $parkA])->save();
    $b->forceFill(['email' => $parkB])->save();

    $a->forceFill(['email' => $toA])->save();
    $b->forceFill(['email' => $toB])->save();
});

$a->refresh();
$b->refresh();

echo "  after:\n";
echo '    '.$show($a)."\n";
echo '    '.$show($b)."\n\n";

/*
 | Read the values back out of the database rather than trusting the objects.
 |
 | The check is "each account now holds the address it was given", not "the old
 | addresses are empty". In a swap the old addresses are exactly where the rows
 | went, so requiring them to be unoccupied reports a successful swap as a
 | failure.
 */
$storedA = $find($toA);
$storedB = $find($toB);

$ok = $storedA !== null
    && $storedB !== null
    && $storedA->id === $a->id
    && $storedB->id === $b->id;

echo '  read back from the database: '.($ok ? 'each account holds the address it was given' : 'MISMATCH, check by hand')."\n";

$stranded = $userModel::query()->where('email', 'like', 'swap-parking-%')->count();

echo '  rows left on a parking address: '.$stranded.($stranded === 0 ? '' : ', check by hand')."\n";

if (! $ok || $stranded > 0) {
    exit(1);
}
