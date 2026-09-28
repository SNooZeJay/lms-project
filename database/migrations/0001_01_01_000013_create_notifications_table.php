<?php

use App\Enums\NotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();

            // The recipient. Notifications are deleted with the account, because
            // a notice addressed to somebody who no longer exists has no reader.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The vocabulary is closed, so it is an enum in the database as well
            // as a cast in PHP. A typo cannot become a type nothing renders.
            $table->enum('type', NotificationType::values());

            // The text is a snapshot taken when the notice was raised, not a
            // lookup. A course that is renamed afterwards does not rewrite the
            // notice that was already sent about it.
            $table->string('title', 160);
            $table->text('body')->nullable();

            // Null for a platform wide notice, which belongs to no course.
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();

            // What the notice is about, so a later slice can group or resolve it
            // without adding a column per kind of thing.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // A path within this application, never a full URL. The write seam
            // refuses anything with a scheme and drops a link the recipient is
            // not allowed to follow, so this column cannot become an open
            // redirect or a javascript: URI.
            $table->string('link')->nullable();

            $table->string('dedup_key', 120)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Idempotency is this index, not a check in the calling code.
            // MySQL treats every NULL as distinct, so a notification that needs
            // no dedup key leaves it null and is never suppressed, and two
            // callers racing on the same key produce one row rather than two.
            $table->unique(['user_id', 'dedup_key'], 'notifications_user_dedup_unique');

            // The centre list, newest first, and its pagination.
            $table->index(['user_id', 'id']);
            // The unread badge, which counts these rows and never loads them.
            $table->index(['user_id', 'read_at']);
            // A course page filtering to its own notices.
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
