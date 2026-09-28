<?php

use App\Enums\ConversationKind;
use App\Enums\ConversationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        /*
         | Three tables, shared by course threads and support threads.
         |
         | One set of storage with two authorization shapes, not two sets. Course
         | threads are scoped by an enrollment, support threads by whoever raised
         | them, and the difference lives in ConversationPolicy. Building them
         | separately is the duplicate system the specification forbids.
         */
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->enum('kind', ConversationKind::values());
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject', 160)->nullable();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ConversationStatus::values())->default('open');
            $table->timestamp('last_message_at')->nullable();

            /*
             | The deterministic identity of a thread.
             |
             | A course thread belongs to a PAIR of people about a course, not to
             | whoever opened it. The first version keyed the unique index on
             | (kind, course_id, requester_id) and that was wrong: when a student
             | and an instructor both reach out about the same course, each row
             | has a different requester_id, so both pass the index and the
             | pair ends up with two threads. The test that pins one thread per
             | pair is what caught it.
             |
             | This column is the pair, written in a fixed order, so the same two
             | people always produce the same string whichever of them opened
             | it. A support thread pairs the person who raised it with 0, since
             | an administrator joining later is a participant and not a second
             | thread. The zero is also why a support thread cannot collide with
             | a course thread even though both keys are plain numbers.
             */
            $table->string('thread_key', 64);

            $table->timestamps();

            $table->unique(['kind', 'thread_key'], 'conversations_deterministic_thread_unique');

            $table->index(['requester_id', 'last_message_at']);
            $table->index(['course_id', 'kind']);
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             | Per thread read state.
             |
             | The unread count is messages newer than this, which is one
             | aggregate over an indexed column. A stored counter would need to
             | be repaired every time a message was deleted and would eventually
             | disagree with the messages it claims to count.
             |
             | It is counted against last_read_message_id and not against
             | last_read_at, and that is a correction. Every timestamp column in
             | this database stores whole seconds, because MySQL's timestamp
             | without a fractional precision rounds to one. Two messages
             | posted in the same second as somebody read the thread would then
             | compare created_at > last_read_at as false, and the messages
             | would be invisible in the unread count. A bigint id is monotonic
             | and has no such gap, so the boundary is exact.
             |
             | last_read_at is kept because a person wants to know when they
             | last looked, and that is a question about time rather than about
             | position. It is display only, and nothing counts against it.
             */
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();

            /*
             | Archiving hides a settled thread without deleting it. A deleted
             | thread is a support conversation with no record, which is the one
             | thing an administration section is not allowed to lose.
             */
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'conversation_participants_member_unique');
            $table->index(['user_id', 'archived_at']);
            $table->index(['conversation_id', 'last_read_message_id'], 'conversation_participants_read_boundary_index');
        });

        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            /*
             | Restrict, not cascade.
             |
             | A deleted account must not erase the record of what was said in a
             | support thread. The message stays, the account goes, and the
             | author is rendered as somebody who is no longer here.
             */
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();

            $table->text('body');

            /*
             | Idempotency, at the source.
             |
             | The composer puts a UUID in a hidden field. A double click, a
             | refresh, or a browser retry sends the same token, and this index
             | drops the second insert. A check in the controller would be
             | racy: two requests can both pass the check.
             */
            $table->uuid('client_token')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->unique(['conversation_id', 'author_id', 'client_token'], 'conversation_messages_token_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
