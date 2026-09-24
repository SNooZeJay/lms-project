<?php

namespace App\Console\Commands;

use App\Contracts\LocalSecretStore;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BootstrapOwner extends Command
{
    protected $signature = 'owner:bootstrap {--show-password : Display the generated password once}';

    protected $description = 'Create the local IT Learning Hub Administrator account.';

    public function handle(LocalSecretStore $secretStore): int
    {
        if (config('app.env') !== 'local') {
            $this->error('The owner bootstrap command is available only in the local environment.');

            return self::FAILURE;
        }

        $name = trim((string) config('owner.name'));
        $email = strtolower(trim((string) config('owner.email')));
        $secretPath = trim((string) config('owner.secret_path'));

        if ($name === '' || mb_strlen($name) > 255) {
            $this->error('OWNER_NAME must be a non-empty value of 255 characters or fewer.');

            return self::FAILURE;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('OWNER_EMAIL must be a valid email address.');

            return self::FAILURE;
        }

        if (! $this->isSafeSecretPath($secretPath)) {
            $this->error('OWNER_SECRET_PATH must be an absolute path outside the repository.');

            return self::FAILURE;
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->profile?->role !== UserRole::Administrator) {
                $this->error('The configured email belongs to a non-Administrator account. No promotion was made.');

                return self::FAILURE;
            }

            $existingUser->update(['name' => $name]);
            $this->info('The Administrator already exists. No password was changed.');

            if ($this->option('show-password') && is_file($secretPath)) {
                $payload = $secretStore->get($secretPath);

                if (($payload['email'] ?? null) === $email && isset($payload['password'])) {
                    $this->warn('Temporary password: '.$payload['password']);
                }
            }

            return self::SUCCESS;
        }

        if (is_file($secretPath)) {
            $this->error('The local secret file already exists. Refusing to overwrite it.');

            return self::FAILURE;
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $createdUser = false;

        try {
            DB::transaction(function () use ($name, $email, $temporaryPassword, &$createdUser): void {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $temporaryPassword,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                $profile = $user->profile()->create();
                $profile->role = UserRole::Administrator;
                $profile->account_status = UserAccountStatus::Active;
                $profile->must_change_password = true;
                $profile->save();

                $createdUser = true;
            });

            $secretStore->put($secretPath, [
                'email' => $email,
                'password' => $temporaryPassword,
                'created_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            if ($createdUser) {
                User::where('email', $email)->delete();
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Administrator created: '.$name);
        $this->line('Temporary password protected with Windows DPAPI at: '.$secretPath);

        if ($this->option('show-password')) {
            $this->warn('Temporary password: '.$temporaryPassword);
        } else {
            $this->comment('Run this command with --show-password only when you are ready to display the password locally.');
        }

        return self::SUCCESS;
    }

    private function generateTemporaryPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    private function isSafeSecretPath(string $path): bool
    {
        if ($path === '' || (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path))) {
            return false;
        }

        $repositoryPath = rtrim(str_replace('/', '\\', strtolower((string) realpath(base_path()))), '\\');
        $candidatePath = str_replace('/', '\\', strtolower($path));

        return ! str_starts_with($candidatePath, $repositoryPath.'\\');
    }
}
