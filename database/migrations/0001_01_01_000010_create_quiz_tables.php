<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules')->restrictOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedInteger('position');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_required')->default(false);
            $table->decimal('passing_score_percent', 5, 2)->default(80.00);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->timestamps();

            $table->unique(['course_id', 'position']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->text('prompt');
            $table->enum('question_type', ['multiple_choice'])->default('multiple_choice');
            $table->unsignedInteger('position');
            $table->decimal('points', 8, 2)->default(1.00);
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'position']);
        });

        Schema::create('quiz_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->text('option_text');
            $table->unsignedInteger('position');
            $table->boolean('is_correct')->default(false);
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'position']);
            $table->index(['question_id', 'is_correct']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->enum('status', ['in_progress', 'passed', 'failed'])->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score_points', 8, 2)->nullable();
            $table->decimal('total_points', 8, 2)->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'student_id', 'attempt_number']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('quiz_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->foreignId('selected_option_id')->constrained('quiz_options')->cascadeOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->decimal('points_awarded', 8, 2)->default(0.00);
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
