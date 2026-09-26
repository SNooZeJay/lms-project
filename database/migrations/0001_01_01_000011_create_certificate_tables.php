<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::create('course_requirements', function (Blueprint $table): void {
            $table->id('course_id');
            $table->boolean('require_all_lessons')->default(true);
            $table->decimal('minimum_lesson_percent', 5, 2)->nullable();
            $table->boolean('require_required_quizzes')->default(true);
            $table->boolean('require_passing_score')->default(true);
            $table->boolean('certificate_enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('certificate_code')->unique();
            $table->string('student_name_snapshot');
            $table->string('course_title_snapshot');
            $table->date('completion_date');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_certificate_id')->nullable()
                ->constrained('certificates')->restrictOnDelete();
            $table->enum('status', ['issued', 'revoked'])->default('issued');
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            // 1 while the certificate is the one valid certificate, NULL once it
            // is revoked. MySQL ignores NULLs in a unique index, so this allows
            // many revoked rows but only one valid certificate per enrollment.
            $table->unsignedTinyInteger('active_slot')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'course_id', 'active_slot'], 'certificates_one_active_per_enrollment');
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('course_requirements');
    }
};
