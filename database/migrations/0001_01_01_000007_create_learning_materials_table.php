<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->enum('material_type', ['text', 'image', 'pdf', 'document', 'code', 'video_link', 'external_link']);
            $table->unsignedInteger('position');
            $table->longText('content_text')->nullable();
            $table->text('external_url')->nullable();
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->timestamps();

            $table->unique(['lesson_id', 'position']);
            $table->unique(['storage_disk', 'storage_path']);
            $table->index('material_type');
        });

        DB::statement(
            'ALTER TABLE learning_materials ADD CONSTRAINT learning_materials_position_check CHECK (position > 0)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_materials');
    }
};
