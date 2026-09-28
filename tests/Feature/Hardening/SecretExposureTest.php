<?php

namespace Tests\Feature\Hardening;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The two boundaries that live outside the PHP application.
 *
 * A request never reaches a controller for a static file, and a secret never
 * reaches a leak through a request at all: it is committed. Both are enforced by
 * configuration in this repository and by the web server in front of it, so
 * neither can be covered by a feature test that sends a request. These tests
 * read the configuration directly instead.
 *
 * The standard being held to is deny by default. Neither rule is a blocklist of
 * the files that happen to be dangerous today; each refuses a category, and the
 * safe case is the one that has to be named explicitly.
 */
class SecretExposureTest extends TestCase
{
    /* ------------------------------------------------------------ gitignore */

    /**
     * @return array<string, array{0: string}>
     */
    public static function secretFileNames(): array
    {
        $names = [
            '.env',
            '.env.local',
            '.env.development',
            '.env.production',
            '.env.staging',
            '.env.test',
            '.env.testing',
            '.env.backup',
            '.env.bak',
            '.env.orig',
            '.env.swp',
            'credentials.json',
            'secrets.yml',
            'server.key',
            'tls.crt',
            'client.p12',
            'id_rsa',
            'auth.json',
        ];

        $cases = [];

        foreach ($names as $name) {
            $cases[$name] = [$name];
        }

        return $cases;
    }

    #[DataProvider('secretFileNames')]
    public function test_a_secret_file_cannot_be_committed(string $name): void
    {
        // git check-ignore answers the question the repository actually cares
        // about: would this file be excluded if somebody created it? Reading the
        // patterns with a regular expression instead would only prove the
        // pattern reads the way this test expects it to.
        exec(
            sprintf('git check-ignore -q %s 2>&1', escapeshellarg($name)),
            $output,
            $status
        );

        $this->assertSame(
            0,
            $status,
            "{$name} is not ignored, so it would be committed. Enumerate the pattern, not the filename."
        );
    }

    public function test_the_environment_template_stays_committed(): void
    {
        // The negation is the point of the rule: an ignored template is a
        // template nobody can use, and the fix for a leaked secret is often to
        // rotate it, which needs the example to stay put.
        exec('git ls-files --error-unmatch .env.example 2>&1', $output, $status);

        $this->assertSame(0, $status, '.env.example is no longer tracked.');
    }

    public function test_the_environment_template_carries_no_real_secret(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        foreach (explode("\n", $example) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // A secret is anything that looks issued rather than described:
            // a provider prefix, a key block, a JWT, or a database URL that
            // carries a password.
            $looksIssued = (bool) preg_match(
                '/^(sk|pk|whsec|gocskx|ghp|glpat|xox[abps])_/i', $value
            ) || str_contains($value, 'BEGIN ')
            || (bool) preg_match('/^eyJ[A-Za-z0-9_-]{10,}/', $value)
            || (bool) preg_match('#\b\w+://[^/\s:]+:[^/\s@]+@#', $value);

            $this->assertFalse(
                $looksIssued,
                "{$key} in .env.example looks like a real issued credential rather than a placeholder."
            );
        }
    }

    public function test_no_secret_shaped_string_reaches_shipped_code(): void
    {
        // The scan is scoped to the surfaces that actually ship: application
        // code, configuration, migrations, views, routes, and the template.
        //
        // It is deliberately not scoped to the whole repository, because the
        // test suite contains strings shaped like credentials on purpose:
        // proving a value is never printed requires a value that looks real. A
        // whole-repository scan matches those fixtures forever, and a check that
        // always fails teaches its reader to ignore it. Test files and
        // documentation are where such a fixture belongs. A credential anywhere
        // else is a leak.
        exec('git grep -In -E -e '
            .escapeshellarg('(sk_live_|pk_live_|whsec_|gocskx_|glpat-|xox[baprs]-|AKIA[0-9A-Z]{16}|BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY)')
            .' -- app config database resources routes bootstrap public .env.example composer.json package.json 2>&1', $output, $status);

        $this->assertSame(
            1,
            $status,
            "A shipped file contains something shaped like a credential:\n  ".implode("\n  ", $output)
        );
    }

    public function test_a_credential_shaped_string_stays_in_tests_or_documentation(): void
    {
        // The counterpart to the check above, and the one that keeps the first
        // honest: if a fixture is ever pasted into application code, this fails
        // even though the narrow scan still passes.
        exec('git grep -In -E -e '
            .escapeshellarg('(sk_live_|pk_live_|whsec_|gocskx_)')
            .' -- . 2>&1', $output, $status);

        $this->assertNotSame([], $output, 'No fixture was found, so this check is not looking at anything.');

        foreach ($output as $line) {
            $path = (string) strtok($line, ':');

            $this->assertTrue(
                str_starts_with($path, 'tests/') || str_starts_with($path, 'docs/'),
                "{$path} contains a credential-shaped string outside the test suite and the documentation."
            );
        }
    }

    public function test_the_real_environment_file_is_not_committed(): void
    {
        exec('git ls-files .env 2>&1', $output, $status);

        $this->assertSame(0, $status, '.env is tracked.');
        $this->assertNotContains('.env', array_map('trim', $output), '.env is tracked.');
    }

    public function test_the_real_environment_file_was_never_committed(): void
    {
        // Removing a file from the working tree does not remove it from history,
        // so the question is asked of every commit rather than of the index.
        exec('git log --all --oneline -- .env 2>&1', $output, $status);

        $this->assertSame(
            '',
            trim(implode("\n", $output)),
            '.env appears in git history, so its values are still recoverable after a rotation.'
        );
    }

    /* ------------------------------------------------------------- htaccess */

    public function test_the_web_server_refuses_dotfiles(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        // Asserted as a literal rather than as a regular expression. The rule is
        // a piece of Apache configuration, and asserting the characters that
        // matter is both easier to read and less likely to pass by accident
        // through a pattern that matched some other part of the file.
        $this->assertStringContainsString(
            'RewriteRule "(^|/)\.',
            $htaccess,
            'public/.htaccess does not refuse dotfiles. The front controller serves any file that already exists in '
            .'the document root, so a .env or a .git directory copied here would be downloadable.'
        );
    }

    public function test_the_web_server_keeps_certificate_validation_working(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        // /.well-known/acme-challenge is how a domain proves it controls a name.
        // A dotfile rule that swallowed it would break certificate renewal in a
        // way that only shows up months later, when a certificate silently
        // fails to renew.
        $this->assertStringContainsString(
            'well-known',
            $htaccess,
            'The dotfile rule does not except /.well-known, which would block certificate validation.'
        );
    }

    public function test_the_web_server_refuses_project_and_credential_files(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        // Checked against the actual shape of the rule, which groups things:
        // extensions appear inside one alternation such as (pem|key|crt|p12),
        // so looking for a literal "\.pem" would fail on a rule that does refuse
        // it. Asserting substrings cannot prove Apache's behaviour, and that is
        // why these needles describe the real text rather than an idealised
        // version of it.
        $needles = [
            '\.env' => 'the environment file',
            'composer\.' => 'the composer manifest',
            'artisan' => 'the artisan entry point',
            '(pem|key|crt|p12|pfx|ppk)' => 'private keys and certificates',
            'credentials' => 'a credentials file',
            'secrets' => 'a secrets file',
            'id_rsa' => 'an SSH private key',
        ];

        foreach ($needles as $needle => $description) {
            $this->assertStringContainsString(
                $needle,
                $htaccess,
                "public/.htaccess does not refuse {$description}."
            );
        }

        // Each rule has to end in a refusal, not a pass-through, or the pattern
        // is decorative.
        $this->assertSame(
            2,
            preg_match_all('/RewriteRule\s+"[^"]+"\s+-\s+\[F,L\]/', $htaccess),
            'public/.htaccess no longer has exactly two refusing rules: the dotfile rule and the file name rule.'
        );
    }

    public function test_the_dotfile_rule_cannot_reject_a_real_request(): void
    {
        // A dotfile rule is only safe because nothing the application serves
        // begins with a dot. That is an assumption about the document root, so
        // it is checked rather than believed.
        $served = glob(public_path('*')) ?: [];

        $this->assertNotEmpty($served, 'The document root appears to be empty, which would make this test meaningless.');

        foreach ($served as $entry) {
            $name = basename($entry);

            $this->assertFalse(
                str_starts_with($name, '.') && $name !== '.htaccess',
                "{$name} is a dotfile in the document root and would be refused by the rule."
            );
        }
    }
}
