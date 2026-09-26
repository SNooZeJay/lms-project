<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->restrictOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'lesson_id']);
            $table->index('lesson_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
