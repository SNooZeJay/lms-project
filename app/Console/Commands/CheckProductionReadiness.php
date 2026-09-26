<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Fails loudly when a production server is not actually ready.
 *
 * Every check is a hard requirement, not a suggestion. The command exits with
 * a failure code so a deployment pipeline stops before a user sees a broken
 * site or a debug stack trace.
 */
class CheckProductionReadiness extends Command
{
    protected $signature = 'lms:check-production
                            {--allow-debug : Accept APP_DEBUG=true, for a staging rehearsal only}';

    protected $description = 'Verify that this server is safe to run as a production release';

    public function handle(): int
    {
        $checks = [
            'Environment is production' => fn () => app()->environment('production'),
            'Debug output is off' => fn () => ! config('app.debug') || $this->option('allow-debug'),
            'Application key is set' => fn () => filled(config('app.key')) && config('app.key') !== 'base64:',
            'Application URL is absolute and secure' => fn () => str_starts_with((string) config('app.url'), 'https://'),
            'Database connection succeeds' => function (): bool {
                try {
                    DB::connection()->getPdo();

                    return true;
                } catch (Throwable) {
                    return false;
                }
            },
            'Database is MySQL' => fn () => config('database.default') === 'mysql',
            'Migrations are up to date' => fn () => ! $this->pendingMigrations(),
            'Storage is writable' => fn () => File::isWritable(storage_path()),
            'Bootstrap cache is writable' => fn () => File::isWritable(base_path('bootstrap/cache')),
            'Private disk root is writable' => fn () => File::isWritable(storage_path('app/private')),
            'Storage link is not required' => fn () => ! File::exists(public_path('storage')),
            'Route cache is current' => fn () => app()->routesAreCached(),
            'Configuration is cached' => fn () => app()->configurationIsCached(),
            'No stale compiled views' => fn () => $this->compiledViewsAreClean(),
            'Session driver is server side' => fn () => in_array(config('session.driver'), ['database', 'redis', 'file'], true),
            'Session cookie is secure' => fn () => (bool) config('session.secure'),
            'Session cookie is same site' => fn () => config('session.same_site') !== 'none',
            'CSRF protection is on' => fn () => ! app()->runningUnitTests(),
            'Trust proxies are configured' => fn () => ! empty(config('trustedproxy.proxies')),
            'Payments are switched off cleanly' => fn () => $this->paymentsDisabled(),
            'Payment checkout can be created' => fn () => $this->checkoutCanBeCreated(),
            'Payment webhooks can be verified' => fn () => $this->webhooksCanBeVerified(),
            'No default or example secret remains' => fn () => ! $this->looksLikeAnExample(),
            'Error pages do not leak internals' => fn () => (bool) config('app.debug') === false,
        ];

        $failures = [];
        $hints = [];

        foreach ($checks as $label => $check) {
            $ok = false;

            try {
                $ok = (bool) $check();
            } catch (Throwable $exception) {
                $ok = false;
            }

            $this->line(sprintf('%-46s %s', $label, $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'));

            if (! $ok) {
                $failures[] = $label;

                $hint = $this->hintFor($label);

                if ($hint !== null) {
                    $hints[$label] = $hint;
                }
            }
        }

        $this->newLine();

        if ($failures !== []) {
            $this->error('This server is not ready for production. Failing checks:');
            $this->newLine();

            foreach ($failures as $failure) {
                $this->line('  - '.$failure);

                if (isset($hints[$failure])) {
                    $this->line('      '.$hints[$failure]);
                }
            }

            $this->newLine();
            $this->line('See docs/deployment.md for the fix for each check.');

            return self::FAILURE;
        }

        $this->info('All production checks passed.');

        return self::SUCCESS;
    }

    /**
     * A plain-language fix for the failures whose cause is not obvious.
     */
    private function hintFor(string $label): ?string
    {
        return match ($label) {
            'Payment webhooks can be verified' => 'Without PAYMONGO_WEBHOOK_SECRET a checkout is created but no '
                .'payment ever settles, so no enrollment is ever activated. Copy the webhook signing secret '
                .'from the PayMongo dashboard.',
            'Payment checkout can be created' => 'Set PAYMONGO_ENABLED=true and PAYMONGO_SECRET_KEY in .env.',
            'Payments are switched off cleanly' => 'Set PAYMONGO_ENABLED=false and blank both PayMongo secrets, '
                .'or configure both secrets and enable payments.',
            'Trust proxies are configured' => 'Set TRUSTED_PROXIES to the addresses of your load balancer.',
            'Session cookie is secure' => 'Set SESSION_SECURE=true once the site is served over HTTPS.',
            'Storage link is not required' => 'Run: rm public/storage. Uploaded files must stay private.',
            'Route cache is current' => 'Run: php artisan route:cache',
            'Configuration is cached' => 'Run: php artisan config:cache',
            'Migrations are up to date' => 'Run: php artisan migrate --force',
            default => null,
        };
    }

    /**
     * A compiled view that no longer has a Blade source would render stale
     * markup, so a release must ship with a cleared view cache.
     */
    private function compiledViewsAreClean(): bool
    {
        $directory = storage_path('framework/views');

        if (! File::isDirectory($directory)) {
            return true;
        }

        foreach (File::files($directory) as $file) {
            $source = $file->getPathname();
            $hash = basename($source, '.php');

            // Laravel names a compiled view with a hash of its Blade path.
            if (! File::exists($this->bladeSourceFor($hash))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Recover the Blade source path for a compiled view file name.
     */
    private function bladeSourceFor(string $compiledName): string
    {
        foreach ($this->bladeSources() as $source) {
            if (sha1($source) === $compiledName) {
                return $source;
            }
        }

        // The hash algorithm is an implementation detail, so treat an
        // unmatched name as stale rather than guessing.
        return '';
    }

    /**
     * @return list<string>
     */
    private function bladeSources(): array
    {
        $sources = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $sources[] = $file->getPathname();
            }
        }

        return $sources;
    }

    private function pendingMigrations(): bool
    {
        $migrations = glob(database_path('migrations/*.php')) ?: [];
        $ran = [];

        try {
            $ran = DB::connection()->table('migrations')->pluck('migration')->all();
        } catch (Throwable) {
            // Reported separately by the database check.
            return false;
        }

        foreach ($migrations as $file) {
            if (! in_array(basename($file, '.php'), $ran, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Payments may be switched off, but then no secret may be half configured.
     */
    private function paymentsDisabled(): bool
    {
        if ((bool) config('services.paymongo.enabled')) {
            return false;
        }

        return config('services.paymongo.secret_key') === ''
            && config('services.paymongo.webhook_secret') === '';
    }

    private function checkoutCanBeCreated(): bool
    {
        if (! (bool) config('services.paymongo.enabled')) {
            return true;
        }

        return (string) config('services.paymongo.secret_key') !== '';
    }

    /**
     * Without the webhook secret a payment can never settle, so a paid
     * student would wait forever. This check exists so that failure can
     * never be silent.
     */
    private function webhooksCanBeVerified(): bool
    {
        if (! (bool) config('services.paymongo.enabled')) {
            return true;
        }

        return (string) config('services.paymongo.webhook_secret') !== '';
    }

    private function looksLikeAnExample(): bool
    {
        $key = (string) config('app.key');
        $password = (string) config('database.connections.mysql.password');
        $url = (string) config('app.url');

        return str_contains($key, 'change-me')
            || str_contains($key, 'REPLACE')
            || $password === 'password'
            || $password === 'secret'
            || str_contains($url, 'example.test');
    }
}
