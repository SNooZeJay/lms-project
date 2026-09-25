<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('event_type', ['role_changed', 'account_status_changed']);
            $table->enum('previous_role', ['student', 'instructor', 'administrator'])->nullable();
            $table->enum('new_role', ['student', 'instructor', 'administrator'])->nullable();
            $table->enum('previous_status', ['active', 'suspended'])->nullable();
            $table->enum('new_status', ['active', 'suspended'])->nullable();
            $table->timestamps();

            $table->index(['actor_id', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
