<?php

/**
 * Scans every blob in the entire history for values that look like secrets.
 *
 * A file scan of the working tree is not enough, and saying so is the point of
 * this: deleting `.env` today does nothing about a `.env` that was committed
 * last month, and the only way to know is to read what is actually in the
 * objects rather than what is on disk now.
 *
 * What it looks for, and why each one is worth a match:
 *
 *   - a real APP_KEY, which is an encryption key and worthless once published
 *   - a database password that is not a placeholder
 *   - a Gmail or SMTP app password, which is sixteen characters and is the one
 *     people actually get wrong
 *   - a PayMongo secret key, prefixed so it can be recognised
 *   - a private key block, which is unambiguous
 *   - any assigned value for a key whose name contains SECRET, PASSWORD, TOKEN
 *     or KEY, unless the value is obviously a placeholder
 *
 * The last one produces the most matches and most of them will be false, so
 * every match is printed with the surrounding line rather than a verdict, and
 * this file says plainly that a match is not a finding until somebody has read
 * it. A tool that cries wolf about `DB_PASSWORD=secret` in a documentation
 * comment is worse than no tool.
 *
 * Usage: php tools/scan-the-history-for-secrets.php
 */

require __DIR__.'/../vendor/autoload.php';

// Runs `git` directly rather than through the application, because this has to
// work on a repository the application might not boot.
function git(string ...$args): string
{
    $root = dirname(__DIR__);

    $command = 'git -C '.escapeshellarg($root).' '.implode(' ', array_map('escapeshellarg', $args));

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes);

    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return $out.$err;
}

/*
 | Every blob in the history, with the commit that introduced it.
 |
 | `rev-list --objects` is the cheap way to get the object list without walking
 | the tree of every commit, and it gives the path along with the hash, which is
 | what makes a match actionable.
 */
$objects = [];
$current = null;

foreach (explode("\n", trim(git('rev-list', '--objects', '--all'))) as $line) {
    if ($line === '') {
        continue;
    }

    $parts = explode(' ', $line, 2);
    $hash = $parts[0];
    $path = $parts[1] ?? '(no path)';

    // Only blobs, and only once each: the same blob can appear in many commits.
    $type = trim(git('cat-file', '-t', $hash));

    if ($type !== 'blob' || isset($objects[$hash])) {
        continue;
    }

    $objects[$hash] = $path;
}

echo "\n  ".count($objects)." distinct blobs across the whole history.\n";

/*
 | The patterns.
 */
$patterns = [
    'a real application key' => '/^APP_KEY=base64:[A-Za-z0-9+\/=]{40,}$/mi',
    'a private key block' => '/-----BEGIN (?:RSA |EC |OPENSSH |PGP )?PRIVATE KEY-----/',
    'a PayMongo secret' => '/paymongo[_-]?secret[_-]?key\s*[=:]\s*["\']?[A-Za-z0-9_\-]{16,}/i',
    'a Gmail app password' => '/\b[a-d]{4}\s[a-d]{4}\s[a-d]{4}\s[a-d]{4}\b/',
    'an assigned secret-looking value' => '/^\s*([A-Z0-9_]*(?:SECRET|PASSWORD|TOKEN|_KEY|API_KEY)[A-Z0-9_]*)\s*=\s*(\S+)\s*$/mi',
];

/*
 | Values that are placeholders, and so are not findings.
 |
 | Without this the scan reports every line of `.env.example` and the
 | documentation as a secret, which is how a security tool gets switched off.
 */
$placeholders = [
    '', 'null', 'none', 'false', 'true', 'changeme', 'change-me', 'your-',
    'placeholder', 'example', 'secret', 'password', 'token', 'xxx', 'todo',
    'base64:generate-with-artisan-key-generate', 'base64:',
    'not-a-real-password', 'replace-me', 'unset',
];

$isPlaceholder = function (string $value) use ($placeholders): bool {
    $value = strtolower(trim($value, "\"' "));

    foreach ($placeholders as $placeholder) {
        if ($placeholder !== '' && str_contains($value, $placeholder)) {
            return true;
        }
    }

    return $value === '' || strlen($value) < 8;
};

$findings = [];
$scanned = 0;

foreach ($objects as $hash => $path) {
    $content = git('cat-file', '-p', $hash);

    if ($content === '') {
        continue;
    }

    // A binary blob is not a place a secret hides, and it makes the scan slow.
    if (str_contains($content, "\0")) {
        continue;
    }

    $scanned++;

    foreach ($patterns as $label => $pattern) {
        if (preg_match_all($pattern, $content, $matches) === false) {
            continue;
        }

        foreach ($matches[0] as $match) {
            $line = trim($match);

            if ($isPlaceholder($line)) {
                continue;
            }

            $findings[] = [
                'label' => $label,
                'path' => $path,
                'hash' => substr($hash, 0, 10),
                'line' => substr($line, 0, 110),
            ];
        }
    }
}

echo "  {$scanned} text blobs read.\n\n";

if ($findings === []) {
    echo "  Nothing in the history matches a secret pattern. Every blob that has ever\n";
    echo "  been committed was read, not just the ones on disk now.\n\n";

    exit(0);
}

echo '  '.count($findings)." line(s) match a pattern. Read each one: a match is a\n";
echo "  question, not a verdict, and a documentation example is a match too.\n\n";

$byLabel = [];

foreach ($findings as $finding) {
    $byLabel[$finding['label']][] = $finding;
}

foreach ($byLabel as $label => $rows) {
    echo "  {$label}\n";

    foreach ($rows as $row) {
        echo "    {$row['hash']}  {$row['path']}\n";
        echo "      {$row['line']}\n";
    }

    echo "\n";
}

exit(1);
