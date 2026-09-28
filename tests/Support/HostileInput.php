<?php

namespace Tests\Support;

/**
 * Hostile input, defined once so every field is tested against the same classes.
 *
 * A generator that lives inside a single test file drifts: the next field gets a
 * different set of classes, and a comparison between two fields stops meaning
 * anything. Keeping the classes here means a value added once is applied to
 * every form in the suite.
 *
 * Two properties matter more than the size of the list.
 *
 * The values are bounded. A fuzz test that sends a hundred megabyte string does
 * not find a validation bug, it finds a memory limit, and it makes the suite
 * slow enough that people stop running it. The longest value here is ten
 * thousand characters, which is past every rule in the application and still
 * small enough to be harmless.
 *
 * The classes are explainable. Each one is here because it breaks a specific
 * thing: multi-byte length counting, output encoding, header construction,
 * integer bounds, or a required field that is present but empty. A generator
 * full of strings nobody can explain is a generator nobody can act on when it
 * finds something.
 *
 * The zero-width and bidirectional marks are included because they are the
 * classes that pass a length check and then render as something shorter or in
 * the opposite order. They are invisible in a diff, which is exactly why they
 * are worth a test.
 */
final class HostileInput
{
    public const ZERO_WIDTH_SPACE = "\u{200B}";

    public const RIGHT_TO_LEFT_OVERRIDE = "\u{202E}";

    public const RIGHT_TO_LEFT_POP = "\u{202C}";

    public const WORD_JOINER = "\u{2060}";

    public const BOM = "\u{FEFF}";

    /**
     * A control that is well formed and should be accepted, so a failure beside
     * it points at the hostile value rather than at the fixture.
     */
    public const CONTROL = 'Introduction to Programming';

    /**
     * @return array<string, string> class name to value
     */
    public static function classes(int $maxLength = 10000): array
    {
        return [
            'control' => self::CONTROL,
            'empty' => '',
            'whitespace only' => "   \t  ",
            'newlines' => "line one\nline two\r\nline three",
            'random letters' => 'asdfghjkl',
            'symbols' => '!@#$%^&*()_+-={}[]|;:"\'<>,.?/',
            'html' => '<script>window.__probe=1</script>',
            'html attribute break' => '"><img src=x onerror=alert(1)>',
            'sql shaped' => "' OR '1'='1",
            'sql statement' => "'; DROP TABLE users; --",
            'template' => '{{ config("app.key") }} @php echo 1; @endphp',
            'japanese' => 'こんにちは世界',
            'arabic' => 'مرحبا بالعالم',
            'russian' => 'Привет мир',
            'emoji' => '😀🚀🔥💻🎓',
            'rtl override' => self::RIGHT_TO_LEFT_OVERRIDE.'admin'.self::RIGHT_TO_LEFT_POP,
            'zero width' => 'ad'.self::ZERO_WIDTH_SPACE.'min',
            'word joiner' => 'ad'.self::WORD_JOINER.'min',
            'byte order mark' => self::BOM.'admin',
            'path traversal' => '../../../../etc/passwd',
            'null byte' => "admin\0.php",
            'crlf' => "admin\r\nSet-Cookie: x=1",
            'numeric string' => '12345',
            'float string' => '1.5',
            'negative' => '-1',
            'boolean word' => 'true',
            'null word' => 'null',
            'json object' => '{"a":1}',
            'json array' => '[]',
            'brace' => '{}',
            'brace mismatch' => '{[',
            'angle mismatch' => '<div><span>',
            'html entity' => '&lt;script&gt;&amp;&quot;',
            '100 chars' => str_repeat('a', 100),
            '500 chars' => str_repeat('b', 500),
            '1000 chars' => str_repeat('c', 1000),
        ];
    }

    /**
     * The boundary pairs that matter for a field with a maximum length.
     *
     * A maximum is only interesting either side of it, so each pair is returned
     * as two values. The application declares 160 for a course title and 5000
     * for a description, so a test of 5000 and 5001 is a real test while a test
     * of 1000 is only interesting for the shorter fields.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function boundaryPairs(int $maxLength = 10000): array
    {
        return [
            'around 100' => [99, 100],
            'around 160' => [159, 160],
            'around 161' => [160, 161],
            'around 255' => [254, 255],
            'around 256' => [255, 256],
            'around 500' => [499, 500],
            'around 501' => [500, 501],
            'around 1000' => [999, 1000],
            'around 5000' => [4999, 5000],
            'around 5001' => [5000, 5001],
            'around the cap' => [$maxLength, $maxLength + 1],
        ];
    }

    /**
     * Values that are not valid for an identifier.
     *
     * @return array<string, string|int>
     */
    public static function badIdentifiers(): array
    {
        return [
            'zero' => 0,
            'negative' => -1,
            'huge' => 999999999,
            'beyond int' => '99999999999999999999',
            'text' => 'abc',
            'sql' => '1 OR 1=1',
            'quoted' => "' OR '1'='1",
            'null byte' => "1\0",
            'float' => '1.5',
            'whitespace' => ' 1 ',
        ];
    }

    /**
     * A single value built from a reproducible seed.
     *
     * Used where the point is a mix rather than a class: several kinds of
     * character in one string, which is what a paste from a real document
     * looks like. The seed is an argument so a failure can be replayed exactly.
     */
    public static function mixed(int $seed, int $length = 400): string
    {
        $alphabet = array_merge(
            range('a', 'z'),
            range('A', 'Z'),
            range('0', '9'),
            [' ', "\n", "\t", '<', '>', '"', "'", '&', '/', '\\', '{', '}', 'こんにちは', 'مرحبا', '😀', self::ZERO_WIDTH_SPACE],
        );

        mt_srand($seed);

        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[mt_rand(0, count($alphabet) - 1)];
        }

        return $out;
    }

    /**
     * Reduce a failing value to the smallest value that still fails.
     *
     * The point of shrinking is that a reported failure is something a
     * developer can paste into a test. "A 400 character mixed string broke
     * something" is not actionable; "the string '<' broke something" is.
     *
     * The caller decides what still fails, because only the caller knows the
     * symptom. This returns a smaller candidate, and the caller keeps asking
     * while the result is still shorter and still fails.
     *
     * @param  callable(string): bool  $stillFails
     * @return array{0: string, 1: int} the reduced value and how many steps it took
     */
    public static function shrink(string $failing, callable $stillFails): array
    {
        $original = $failing;
        $steps = 0;
        $best = $failing;

        // Halve, then halve again, which reaches a short string quickly without
        // needing one round trip per character.
        while (strlen($best) > 1) {
            $candidate = substr($best, 0, intdiv(strlen($best), 2));
            $steps++;

            if (! $stillFails($candidate)) {
                break;
            }

            $best = $candidate;
        }

        // Then a linear pass, because halving stops as soon as a shorter value
        // stops failing, and a single offending character in the middle of a
        // long string is exactly the case halving cannot reach.
        for ($i = 0; $i < strlen($best); $i++) {
            $candidate = $best;
            $steps++;

            if ($stillFails($candidate)) {
                $best = $candidate;
            }
        }

        return [$best === '' ? $original : $best, $steps];
    }
}
