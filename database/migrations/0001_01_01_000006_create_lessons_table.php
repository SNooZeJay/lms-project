<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            $table->longText('content_text')->nullable();
            $table->unsignedInteger('position');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->timestamps();

            $table->unique(['module_id', 'slug']);
            $table->unique(['module_id', 'position']);
            $table->index('status');
        });

        DB::statement(
            'ALTER TABLE lessons ADD CONSTRAINT lessons_position_check CHECK (position > 0)',
        );
        DB::statement(
            'ALTER TABLE lessons ADD CONSTRAINT lessons_estimated_minutes_check CHECK (estimated_minutes IS NULL OR estimated_minutes > 0)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
