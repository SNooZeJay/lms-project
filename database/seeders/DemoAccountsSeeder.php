<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The three accounts a demonstration is run as.
 *
 * Written because the catalog seeder only created the instructor, so a fresh
 * machine could run `db:seed`, end up with five courses and a full curriculum,
 * and still have no way to demonstrate the student or the administrator side of
 * the product at all. Two thirds of the application could not be shown by anyone
 * following the documented setup.
 *
 * The other three accounts existed on the development machine because a probe had
 * made them, and they were not in the repository in any form, so a laptop
 * started from a clone had three fewer accounts than the PC and no documentation
 * that said so.
 *
 * IDEMPOTENT, AND NOT IDEMPOTENT ON PURPOSE
 *
 * Running this twice is safe: an account that already exists is left exactly as
 * it is, apart from its role. That matters because re-seeding a database
 * somebody has been working in must not reset their password, and must not
 * quietly demote an Administrator who is about to sign in and find the
 * dashboard gone.
 *
 * So the rule is the same one the catalog seeder uses: the role is enforced, the
 * name and password are not. An account that already holds a more senior role is
 * left alone entirely.
 *
 * THE PASSWORD
 *
 * Read from `DEV_ACCOUNT_PASSWORD` rather than generated, because a generated
 * password printed to a terminal is a password nobody can type into a sign in
 * form on a second machine. When the variable is absent a password is generated,
 * printed once, and said out loud in the same line, so the account is usable and
 * the reader knows it.
 *
 * These are demonstration accounts. `APP_ENV=production` refuses to run this at
 * all rather than creating three people with a known password on a real
 * installation.
 */
class DemoAccountsSeeder extends Seeder
{
    /**
     * @var list<array{email: string, name: string, role: UserRole}>
     */
    private const ACCOUNTS = [
        [
            'email' => 'admin@lms.test',
            'name' => 'Administrator Demo',
            'role' => UserRole::Administrator,
        ],
        [
            'email' => 'instructor@lms.test',
            'name' => 'Instructor Demo',
            'role' => UserRole::Instructor,
        ],
        [
            'email' => 'student@lms.test',
            'name' => 'Student Demo',
            'role' => UserRole::Student,
        ],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Demo accounts were not created. This is a production environment.');

            return;
        }

        $configured = (string) env('DEV_ACCOUNT_PASSWORD', '');
        $generated = '';

        if ($configured === '') {
            $generated = $this->generatePassword();
            $configured = $generated;
        }

        foreach (self::ACCOUNTS as $account) {
            $this->account($account['email'], $account['name'], $account['role'], $configured);
        }

        if ($generated !== '') {
            $this->command?->info('DEV_ACCOUNT_PASSWORD was not set, so one was generated.');
            $this->command?->line('');
            $this->command?->line('  Demonstration password: '.$generated);
            $this->command?->line('  Put it in .env as DEV_ACCOUNT_PASSWORD to keep it.');
            $this->command?->line('');
        }

        $this->command?->info('Three demonstration accounts are ready: administrator, instructor, student.');
    }

    /**
     * Creates the account, or enforces its role on one that already exists.
     *
     * Built through the factory rather than with `new User` and a fill, because
     * the profile is created by the factory's `afterCreating` hook and not by the
     * model. Setting the attributes by hand left a user with no profile at all,
     * and the next line then failed on a null relation with a message that said
     * nothing about which of the two mistakes had been made.
     *
     * The factory also marks the account active and not requiring a password
     * change, which is what a demonstration account wants: sign in, and land on
     * the dashboard.
     */
    private function account(string $email, string $name, UserRole $role, string $password): void
    {
        $existing = User::query()->where('email', $email)->first();
        if ($existing !== null) {
            $this->enforceRole($existing, $role);

            return;
        }
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            // Passed through the factory's cast, which hashes it. Hashed by hand
            // here instead, the account would be created with a plaintext
            // password and would look right until the first sign in compared it.
            'password' => $password,
        ]);
        $user->profile->forceFill(['role' => $role])->save();
    }

    /**
     * Enforces a role without reshaping anything else.
     */
    private function enforceRole(User $user, UserRole $role): void
    {
        /*
         | A profile is created if it is missing.
         |
         | An account without one is a row the schema should not allow, and one
         | exists here because an earlier version of this seeder built users by
         | hand and the profile was made by a factory hook that never ran. Reading
         | the role then failed on a null relation, and the seeder refused to run
         | on the very database it was meant to be repairing.
         |
         | The profile is made here rather than the account deleted, because a
         | seeder removing a person over its own earlier mistake is worse than
         | repairing the row.
         */
        if (! $user->profile()->exists()) {
            $user->profile()->create(['role' => $role]);

            return;
        }

        $current = $user->profile->role;

        // Never demote, and never promote past what a seeder should be doing.
        // A more senior role is somebody's real decision and is left alone.
        if ($current === $role) {
            return;
        }

        $order = [
            UserRole::Student->value => 0,
            UserRole::Instructor->value => 1,
            UserRole::Administrator->value => 2,
        ];

        if (($order[$current->value] ?? 0) > ($order[$role->value] ?? 0)) {
            $this->command?->warn("{$user->email} is a {$current->value} and was left as it is.");

            return;
        }

        $user->profile->forceFill(['role' => $role])->save();
    }

    /**
     * A password a person can type, and that a scan will not recognise as one.
     *
     * Sixteen characters from a set with no ambiguous pairs, because a
     * demonstration is watched on a projector by somebody who has to type this
     * in once and does not want to be defeated by a lowercase l.
     */
    private function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < 16; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
