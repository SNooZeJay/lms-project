<?php

namespace Tests\Feature\Auth;

use App\Contracts\LocalSecretStore;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_command_creates_a_verified_administrator_with_a_protected_temporary_password(): void
    {
        $store = new InMemorySecretStore;
        config([
            'app.env' => 'local',
            'owner.name' => 'Jayzee Bautista',
            'owner.email' => 'bautista.jayzee@ncst.edu.ph',
            'owner.secret_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'it-learning-hub-owner-test.json',
        ]);
        app()->instance(LocalSecretStore::class, $store);

        $exitCode = Artisan::call('owner:bootstrap');

        $this->assertSame(0, $exitCode);
        $user = User::where('email', 'bautista.jayzee@ncst.edu.ph')->firstOrFail();

        $this->assertSame('Jayzee Bautista', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(UserRole::Administrator, $user->profile->role);
        $this->assertSame(UserAccountStatus::Active, $user->profile->account_status);
        $this->assertTrue($user->profile->must_change_password);
        $this->assertNotEmpty($store->payload['password'] ?? null);
        $this->assertTrue(Hash::check($store->payload['password'], $user->password));
        $this->assertStringNotContainsString($store->payload['password'], Artisan::output());
    }

    public function test_local_command_refuses_to_promote_an_existing_student(): void
    {
        $student = User::factory()->create([
            'email' => 'bautista.jayzee@ncst.edu.ph',
        ]);

        config([
            'app.env' => 'local',
            'owner.name' => 'Jayzee Bautista',
            'owner.email' => 'bautista.jayzee@ncst.edu.ph',
            'owner.secret_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'it-learning-hub-owner-test.json',
        ]);
        app()->instance(LocalSecretStore::class, new InMemorySecretStore);

        $exitCode = Artisan::call('owner:bootstrap');

        $this->assertSame(1, $exitCode);
        $this->assertSame(UserRole::Student, $student->fresh()->profile->role);
    }
}

class InMemorySecretStore implements LocalSecretStore
{
    /** @var array<string, mixed> */
    public array $payload = [];

    public function put(string $path, array $payload): void
    {
        $this->payload = $payload;
    }

    public function get(string $path): array
    {
        return $this->payload;
    }
}
