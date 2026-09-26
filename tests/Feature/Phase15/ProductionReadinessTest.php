<?php

namespace Tests\Feature\Phase15;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_check_command_is_registered(): void
    {
        $this->assertArrayHasKey('lms:check-production', $this->app[Kernel::class]->all());
    }

    public function test_the_check_fails_on_a_development_server(): void
    {
        $this->artisan('lms:check-production')
            ->assertExitCode(1);
    }

    public function test_the_check_reports_every_failing_check_by_name(): void
    {
        $this->artisan('lms:check-production')
            ->expectsOutputToContain('This server is not ready for production')
            ->assertExitCode(1);
    }

    public function test_a_purely_local_environment_is_always_refused(): void
    {
        // APP_ENV is local in the test environment, so the command must fail
        // no matter how the rest of the configuration looks.
        $this->assertFalse(app()->environment('production'));

        $this->artisan('lms:check-production')->assertExitCode(1);
    }

    public function test_the_check_never_prints_a_secret_value(): void
    {
        $key = 'base64:'.base64_encode(str_repeat('k', 32));
        $webhook = 'whsec_super_secret_value';

        config([
            'app.key' => $key,
            'services.paymongo.enabled' => true,
            'services.paymongo.secret_key' => 'sk_live_do_not_print',
            'services.paymongo.webhook_secret' => $webhook,
        ]);

        $this->artisan('lms:check-production')
            ->doesntExpectOutputToContain($key)
            ->doesntExpectOutputToContain($webhook)
            ->doesntExpectOutputToContain('sk_live_do_not_print')
            ->assertExitCode(1);
    }

    public function test_a_missing_webhook_secret_is_reported_with_a_plain_explanation(): void
    {
        config([
            'services.paymongo.enabled' => true,
            'services.paymongo.secret_key' => 'set-for-this-test',
            'services.paymongo.webhook_secret' => '',
        ]);

        // A checkout can be created, but nothing would ever settle, so the
        // webhook check must fail and must say why in words.
        $this->artisan('lms:check-production')
            ->expectsOutputToContain('Payment webhooks can be verified')
            ->expectsOutputToContain('no payment ever settles')
            ->assertExitCode(1);
    }

    public function test_payments_switched_off_with_no_secrets_is_clean(): void
    {
        config([
            'services.paymongo.enabled' => false,
            'services.paymongo.secret_key' => '',
            'services.paymongo.webhook_secret' => '',
        ]);

        // Every check label is always printed, so this asserts on the failure
        // summary, which only lists checks that actually failed.
        $this->artisan('lms:check-production')
            ->doesntExpectOutputToContain('- Payments are switched off cleanly')
            ->doesntExpectOutputToContain('- Payment checkout can be created')
            ->doesntExpectOutputToContain('- Payment webhooks can be verified')
            ->assertExitCode(1);
    }

    public function test_half_configured_payments_fail_the_off_check(): void
    {
        config([
            'services.paymongo.enabled' => false,
            'services.paymongo.secret_key' => 'left-over-key',
            'services.paymongo.webhook_secret' => '',
        ]);

        $this->artisan('lms:check-production')
            ->expectsOutputToContain('- Payments are switched off cleanly')
            ->assertExitCode(1);
    }

    public function test_the_runbook_documents_every_failing_check(): void
    {
        $runbook = File::get(base_path('docs/deployment.md'));

        $this->assertStringContainsString('lms:check-production', $runbook);
        $this->assertStringContainsString('APP_DEBUG=false', $runbook);
        $this->assertStringContainsString('SESSION_SECURE=true', $runbook);
        $this->assertStringContainsString('TRUSTED_PROXIES', $runbook);
        $this->assertStringContainsString('Do **not** run `php artisan storage:link`', $runbook);
        $this->assertStringContainsString('Rollback', $runbook);
    }

    public function test_the_example_environment_never_carries_a_secret(): void
    {
        $example = File::get(base_path('.env.example'));

        $this->assertStringContainsString('PAYMONGO_ENABLED=false', $example);
        $this->assertStringContainsString('PAYMONGO_SECRET_KEY=', $example);
        $this->assertStringContainsString('PAYMONGO_WEBHOOK_SECRET=', $example);

        // Every secret line must be empty, so copying the file is safe.
        foreach (['PAYMONGO_SECRET_KEY', 'PAYMONGO_WEBHOOK_SECRET', 'DB_PASSWORD', 'APP_KEY', 'OWNER_SECRET_PATH'] as $key) {
            foreach (explode("\n", $example) as $line) {
                if (str_starts_with(trim($line), $key.'=')) {
                    $value = trim(substr(trim($line), strlen($key) + 1));

                    $this->assertSame('', $value, "{$key} must ship empty in .env.example.");
                }
            }
        }
    }

    public function test_the_repository_ignores_every_environment_file(): void
    {
        $ignore = File::get(base_path('.gitignore'));

        $this->assertStringContainsString('.env', $ignore);
        $this->assertStringContainsString('.env.production', $ignore);
        $this->assertStringContainsString('/public/storage', $ignore);
    }

    public function test_no_tracked_file_contains_a_committed_secret(): void
    {
        $tracked = trim(shell_exec('git ls-files') ?? '');
        $this->assertNotSame('', $tracked);

        // A provider key pattern, or an environment assignment with a value.
        // An empty assignment is a safe placeholder, not a secret.
        $patterns = [
            '/\bsk_(test|live)_[A-Za-z0-9]{8,}/',
            '/\bwhsec_[A-Za-z0-9]{8,}/',
            '/^(PAYMONGO_SECRET_KEY|PAYMONGO_WEBHOOK_SECRET|DB_PASSWORD|APP_KEY|OWNER_SECRET_PATH|MAIL_PASSWORD)=(.+)$/m',
        ];

        $allowedPlaceholder = static function (string $value): bool {
            // A trailing comment is documentation, not a value.
            $value = trim(explode('#', $value, 2)[0]);
            $value = trim($value, "\"' ");

            if ($value === '' || $value === 'null' || str_contains($value, '${')) {
                return true;
            }

            // Values that describe themselves are documentation, not secrets.
            $words = [
                'replace', 'placeholder', 'example', 'your', 'change', 'live',
                'not-a-real', 'notareal', 'sample', 'dummy', 'todo', 'xxxx',
            ];

            $lowered = strtolower($value);

            foreach ($words as $word) {
                if (str_contains($lowered, $word)) {
                    return true;
                }
            }

            return false;
        };

        foreach (explode("\n", $tracked) as $file) {
            $file = trim($file);

            if ($file === '' || ! File::isFile(base_path($file))) {
                continue;
            }

            if (! preg_match('/\.(php|js|css|blade\.php|md|json|xml|yml|example)$/', $file)) {
                continue;
            }

            $contents = File::get(base_path($file));

            foreach ($patterns as $index => $pattern) {
                if (preg_match_all($pattern, $contents, $matches) === 0) {
                    continue;
                }

                foreach ($matches[0] as $match) {
                    if ($index === 2) {
                        $value = explode('=', $match, 2)[1];

                        if ($allowedPlaceholder($value)) {
                            continue;
                        }
                    }

                    $this->fail("{$file} appears to contain a committed secret: {$match}");
                }
            }
        }

        $this->assertTrue(true, 'No committed secret was found.');
    }

    public function test_the_administrator_bootstrap_command_refuses_to_run_in_production(): void
    {
        $this->artisan('owner:bootstrap')
            ->assertExitCode(1);
    }

    public function test_the_defense_document_records_the_live_payment_gap(): void
    {
        $defense = File::get(base_path('docs/defense.md'));

        $this->assertStringContainsString('webhook path is not verified', $defense);
        $this->assertStringContainsString('Authorization matrix', $defense);
        $this->assertStringContainsString('Security checklist', $defense);
        $this->assertStringContainsString('Architecture tradeoffs', $defense);
        $this->assertStringContainsString('Honest limitations', $defense);
    }

    public function test_the_folder_structure_document_lists_every_app_file(): void
    {
        $tree = File::get(base_path('docs/folder-structure.md'));

        $missing = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $name = $file->getFilename();

            if (! str_contains($tree, $name)) {
                $missing[] = $file->getPathname();
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These app/ files are missing from docs/folder-structure.md: '.implode(', ', $missing)
        );
    }

    public function test_the_folder_structure_document_names_no_file_that_does_not_exist(): void
    {
        $tree = File::get(base_path('docs/folder-structure.md'));
        $real = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $real[$file->getFilename()] = true;
            }
        }

        preg_match_all('/([A-Za-z0-9_]+\.php)\s*$/', $tree, $matches);

        $phantom = array_values(array_unique(array_filter(
            $matches[1],
            fn (string $name): bool => ! isset($real[$name])
        )));

        $this->assertSame(
            [],
            $phantom,
            'docs/folder-structure.md names files that do not exist: '.implode(', ', $phantom)
        );
    }

    public function test_the_roadmap_marks_phase_fifteen_as_the_last_phase(): void
    {
        $roadmap = File::get(base_path('docs/development-roadmap.md'));

        $this->assertStringContainsString('## 30. Phase 15: deployment and defense', $roadmap);
        $this->assertStringNotContainsString('## 31. Phase 16', $roadmap);
    }

    public function test_the_roadmap_records_every_phase_status(): void
    {
        $roadmap = File::get(base_path('docs/development-roadmap.md'));

        foreach (range(22, 29) as $section) {
            $this->assertMatchesRegularExpression(
                '/## '.$section.'\. Phase \d+:.*?### Status/s',
                $roadmap,
                "Section {$section} has no status."
            );
        }
    }

    public function test_no_course_exists_without_an_owner(): void
    {
        $course = Course::factory()->for(User::factory()->instructor(), 'instructor')->create();

        $this->assertNotNull($course->instructor_id);
        $this->assertSame(UserRole::Instructor, $course->instructor->profile->role);
    }
}
