<?php

namespace Tests\Unit\Support;

use App\Support\WindowsDpapiSecretStore;
use Illuminate\Support\Str;
use Tests\TestCase;

class WindowsDpapiSecretStoreTest extends TestCase
{
    public function test_dpapi_store_encrypts_and_decrypts_a_local_payload(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows DPAPI is only available on Windows.');
        }

        $path = storage_path('framework/testing/owner-secret-'.Str::uuid().'.json');
        $payload = [
            'email' => 'test@example.test',
            'password' => 'temporary-test-password',
        ];
        $store = new WindowsDpapiSecretStore;

        try {
            $store->put($path, $payload);
            $protectedContents = file_get_contents($path);

            $this->assertIsString($protectedContents);
            $this->assertStringNotContainsString($payload['password'], $protectedContents);
            $this->assertSame($payload, $store->get($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
