<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('learning_objectives')->nullable();
            $table->string('category')->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->enum('course_type', ['free', 'paid'])->default('free');
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('PHP');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('thumbnail_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('instructor_id');
            $table->index(['status', 'course_type']);
            $table->index('category');
            $table->index('published_at');
        });

        DB::statement(
            "ALTER TABLE courses ADD CONSTRAINT courses_price_type_check CHECK ((course_type = 'free' AND price_minor = 0) OR (course_type = 'paid' AND price_minor > 0))",
        );
        DB::statement(
            "ALTER TABLE courses ADD CONSTRAINT courses_currency_check CHECK (currency = 'PHP')",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
