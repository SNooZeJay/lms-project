<?php

use Illuminate\Contracts\Console\Kernel;

/*
 | A guard against stripped imports.
 |
 | Two `use` statements in this project were reported written and then were not
 | in the file, and the failure mode is quiet: PHP does not complain about an
 | unimported short class name, it resolves it against the current namespace and
 | only complains at the point of use, as a BindingResolutionException deep in a
 | view render. So a missing import looks like a broken feature rather than a
 | broken file.
 |
 | This walks app/ and reports every short class name that is used but neither
 | imported nor defined in the same file. The list is meant to be short enough to
 | read, because an unread list is not a guard.
 |
 | Run: php tools/check-imports.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$root = dirname(__DIR__);

/** The trees to walk. app/ and tests/, because a stripped import in a test fails the same way. */
$trees = [$root.'/app', $root.'/tests'];

/** Names PHP itself provides, so a false positive does not crowd out a real one. */
$known = [
    'self', 'static', 'parent', 'PHP_EOL', 'Throwable', 'Exception', 'Error',
    'ArrayAccess', 'Countable', 'IteratorAggregate', 'JsonSerializable', 'Stringable',
    'Closure', 'Generator', 'Traversable', 'DateTimeInterface', 'DateTimeImmutable',
    'DateTime', 'DateInterval', 'DateTimeZone', 'UnitEnum', 'BackedEnum', 'stdClass',
    'RuntimeException', 'LogicException', 'InvalidArgumentException',
    'UnexpectedValueException', 'OutOfBoundsException', 'RangeException',
    'TypeError', 'ValueError', 'JsonException', 'OverflowException', 'UnderflowException',
    'DateTimeException', 'SplFileObject', 'NumberFormatter', 'IntlDateFormatter',
    // Global SPL classes, referenced without a namespace.
    'RecursiveDirectoryIterator', 'RecursiveIteratorIterator', 'DirectoryIterator',
    'DOMDocument', 'DOMElement', 'DOMNodeList',
    // Windows only. SecureString comes from the DPAPI extension, and String is
    // the interface it implements.
    'SecureString', 'String',
    // Fortify contract aliases, imported with `use` inside a method body rather
    // than at the top of the file, which is where this scanner does not look.
    'FailedPasswordResetLinkRequestResponseContract', 'LoginResponseContract',
    'FailedPasswordResetLinkRequestResponse', 'ResetPasswordResponseContract',
];

$skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

/** The token kinds a class name can arrive as. */
$nameTokens = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

/**
 * The index of the next meaningful token after $i, or null.
 *
 * The index and not the token, because an alias has to be located relative to
 * the name it renames, and an offset from the `use` keyword is not that
 * position.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
$nextSignificantIndex = static function (array $tokens, int $i) use ($skip): ?int {
    $count = count($tokens);

    for ($j = $i + 1; $j < $count; $j++) {
        if (is_array($tokens[$j]) && in_array($tokens[$j][0], $skip, true)) {
            continue;
        }

        return $j;
    }

    return null;
};

/**
 * The next meaningful token after $i.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array{0: int, 1: string, 2: int}|string|null
 */
$nextSignificant = static function (array $tokens, int $i) use ($nextSignificantIndex) {
    $index = $nextSignificantIndex($tokens, $i);

    return $index === null ? null : $tokens[$index];
};

$problems = [];

$appFiles = [];

foreach ($trees as $tree) {
    if (! is_dir($tree)) {
        continue;
    }

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tree)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $appFiles[] = $file->getPathname();
        }
    }
}

foreach ($appFiles as $path) {
    $code = (string) file_get_contents($path);
    $tokens = token_get_all($code);
    $count = count($tokens);

    $namespace = '';
    $imports = [];
    $defines = [];
    $bare = '';

    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];

        if (! is_array($t)) {
            $bare .= $t;

            continue;
        }

        if ($t[0] === T_NAMESPACE) {
            // The token text is the word "namespace"; the name is the next one.
            $after = $nextSignificant($tokens, $i);
            $namespace = is_array($after) ? $after[1] : '';

            continue;
        }

        if ($t[0] === T_USE) {
            // A qualified name is one token in PHP 8, not a T_STRING followed by
            // separators, so all four name token kinds have to be accepted.
            $after = $nextSignificant($tokens, $i);

            if (! is_array($after) || ! in_array($after[0], $nameTokens, true)) {
                continue;
            }

            /*
             | An aliased import has to resolve to the alias.
             |
             | `as` is its own token, so the alias is the next name after it.
             | `use Illuminate\Database\Query\Builder as QueryBuilder;` makes
             | QueryBuilder available and the name token alone records Builder,
             | which is not what any file then uses. The guard reported a
             | perfectly good import as missing, and a guard that cries wolf is
             | worse than none.
             */
            $imported = ltrim($after[1], '\\');
            $nameIndex = $nextSignificantIndex($tokens, $i);
            $asIndex = $nameIndex === null ? null : $nextSignificantIndex($tokens, $nameIndex);

            if ($asIndex !== null && is_array($tokens[$asIndex]) && $tokens[$asIndex][0] === T_AS) {
                $aliasIndex = $nextSignificantIndex($tokens, $asIndex);

                if ($aliasIndex !== null && is_array($tokens[$aliasIndex]) && $tokens[$aliasIndex][0] === T_STRING) {
                    $imports[] = $tokens[$aliasIndex][1];

                    continue;
                }
            }

            $imports[] = $imported;

            continue;
        }

        if (in_array($t[0], [T_CLASS, T_INTERFACE, T_ENUM, T_TRAIT], true)) {
            $after = $nextSignificant($tokens, $i);

            if (is_array($after) && $after[0] === T_STRING) {
                $defines[] = $after[1];
            }
        }

        // A class named in prose is not a reference to a class.
        if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $bare .= $t[1];
    }

    /*
     | The short name is the part after the last separator.
     |
     | Only when there is one. An alias has no separator, strrpos returns false,
     | and false + 1 is 1, so substr quietly turned QueryBuilder into
     | ueryBuilder and the guard reported a good import as missing. That is the
     | second time this rule has been wrong about a file that was entirely
     | correct, which is why it is worth the extra branch.
     */
    $importedShort = array_map(
        static function (string $import): string {
            $separator = strrpos($import, '\\');

            return $separator === false ? $import : substr($import, $separator + 1);
        },
        $imports
    );

    preg_match_all('/(?<![\\\\\$>a-zA-Z0-9_])([A-Z][a-zA-Z0-9_]+)\s*::/', $bare, $const);
    preg_match_all('/\bnew\s+\\\\?([A-Z][a-zA-Z0-9_]+)/', $bare, $new);
    preg_match_all('/\b([A-Z][a-zA-Z0-9_]+)\s+\$[a-z]/', $bare, $hint);
    preg_match_all('/\bcatch\s*\(\s*\\\\?([A-Z][a-zA-Z0-9_]*)/', $bare, $catch);

    $used = array_unique(array_merge($const[1], $new[1], $hint[1], $catch[1]));

    foreach ($used as $name) {
        if (in_array($name, $importedShort, true)
            || in_array($name, $defines, true)
            || in_array($name, $known, true)) {
            continue;
        }

        $probe = $namespace.'\\'.$name;

        if (class_exists($probe) || interface_exists($probe) || enum_exists($probe) || trait_exists($probe)) {
            continue;
        }

        $problems[] = str_replace($root.'\\', '', $path).': '.$name;
    }

    /*
     | A test class that writes to the database and applies no isolation trait.
     |
     | This is not a style preference. RefreshDatabase has been silently stripped
     | from a test file by the same mechanism that strips imports, and a test
     | class without it commits every row it writes. That leaked enrollments into
     | 88 unrelated tests in the webhook suite, with counts that grew on every
     | run and a failure total that moved depending on execution order. Nothing in
     | the failing tests pointed at the cause.
     |
     | So it is checked here, where a missing import is also checked.
     |
     | The separator is normalised because the paths carry backslashes on
     | Windows, and a check written for forward slashes silently matches nothing
     | on the platform this project is developed on. The first version of this
     | rule did exactly that and reported a clean tree while the fault was
     | present, which is the worst failure a guard can have.
     */
    $normalised = str_replace('\\', '/', $path);

    if (str_contains($normalised, '/tests/') && preg_match('/extends\s+TestCase\b/', $code)) {
        $isolates = preg_match('/^\s+use\s+(RefreshDatabase|DatabaseMigrations|DatabaseTransactions|DatabaseTruncation)\s*;/m', $code);

        /*
         | Matched on writes that actually reach the database.
         |
         | A bare app( was in the first version and it produced a false positive
         | on a test that only resolves a middleware out of the container. A
         | guard that cries wolf gets ignored, so the pattern is narrow: a
         | factory, a save, an insert, or a query builder.
         */
        $writes = preg_match(
            '/::factory\(\)|->save\(\)|->insert\(|->create\(|::query\(\)|\bDB::/',
            $code
        );

        if ($writes && ! $isolates) {
            $problems[] = str_replace($root.'\\', '', $path)
                .': writes to the database but applies no RefreshDatabase, DatabaseMigrations or DatabaseTransactions trait, so it commits';
        }
    }
}

sort($problems);

if ($problems === []) {
    echo "  every short class name is imported or defined in its file\n";
} else {
    echo '  '.count($problems)." short class name(s) used without an import:\n";

    foreach ($problems as $p) {
        echo "    $p\n";
    }
}
