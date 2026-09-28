<?php

use App\Enums\AnnouncementScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        /*
         | An announcement is a row and nothing else.
         |
         | No read_at, because read state is the notification row's and having two
         | would mean two places to mark it and a way for them to disagree. No
         | archived_at and no draft state, because the plan defers both. A
         | published announcement is immutable: there is no edit, so nobody has
         | to be told that what they read changed underneath them.
         */
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->enum('scope', AnnouncementScope::values());
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->timestamp('published_at');

            $table->timestamps();

            /*
             | The three reads this table actually serves.
             |
             | A course page lists its own announcements newest first. The platform
             | list spans every course, so it needs published_at on its own. And
             | "what did I publish" is a real question, because only the author may
             | withdraw one.
             */
            $table->index(['scope', 'course_id', 'published_at'], 'announcements_course_page_index');
            $table->index('published_at');
            $table->index(['author_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
