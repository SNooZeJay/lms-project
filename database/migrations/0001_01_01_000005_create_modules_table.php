<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();

            $table->unique(['course_id', 'position']);
            $table->index('status');
        });

        DB::statement(
            'ALTER TABLE modules ADD CONSTRAINT modules_position_check CHECK (position > 0)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
