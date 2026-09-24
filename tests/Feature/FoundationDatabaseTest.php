<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_connection_uses_the_approved_mysql_test_database(): void
    {
        $version = DB::selectOne('SELECT VERSION() AS version')->version;

        $this->assertSame('lms_test', DB::connection()->getDatabaseName());
        $this->assertStringStartsWith('8.4.', $version);
    }

    public function test_phase_two_identity_schema_contains_no_lms_business_tables(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
        $this->assertTrue(Schema::hasTable('profiles'));

        $this->assertTrue(Schema::hasColumns('profiles', [
            'user_id',
            'role',
            'account_status',
            'must_change_password',
            'bio',
        ]));

        $this->assertFalse(Schema::hasTable('courses'));
        $this->assertFalse(Schema::hasTable('enrollments'));
        $this->assertFalse(Schema::hasTable('payments'));
        $this->assertFalse(Schema::hasTable('quizzes'));
        $this->assertFalse(Schema::hasTable('certificates'));
    }
}
