<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignments, and the work students hand in against them.
 *
 * Written because a course you cannot be marked in is a course you can only read,
 * and every quiz in this application is auto graded. Manual marking is the one
 * assessment path the system did not have, and it is the one real courses need.
 *
 * WHAT IS DELIBERATELY ABSENT
 *
 * There is no due date. Nothing in this application stores one, and a column
 * that is always empty is worse than no column: it shows on the screen, invites
 * the question, and answers nothing. An assignment is published or it is not.
 *
 * There is no percentage or grade letter. The mark is the instructor's number,
 * in the instructor's own mark scale, and converting it into a letter would be
 * a decision this application is not entitled to make on the school's behalf.
 *
 * WHY A SUBMISSION IS COPIED RATHER THAN LINKED
 *
 * A submission points at a file on the private disk, and that file is named by a
 * generated path, never by the name the student gave it. If a student uploads
 * `final.docx` and then re-uploads `final-final.docx`, the second upload replaces
 * the first rather than adding to it, because one student has one submission per
 * assignment until it is graded. A version history was considered and not built:
 * nobody asks to see the draft they were going to withdraw.
 *
 * THE STATE COLUMN IS THE WHOLE POINT
 *
 * `pending` is what the student sees as "submitted, waiting for checking". It is
 * a real column rather than a derived one so that "is this marked" is a question
 * the database answers, not one the interface guesses at from a null score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();

            // An assignment belongs to a lesson, because a lesson is the unit a
            // student reaches for. It is not on the course: an assignment on the
            // course has no place to be read from.
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();

            /*
             | Restricted, not cascaded.
             |
             | Every content-author column in this schema restricts, and this one
             | has to as well: a brief belongs to the school rather than to one
             | person's account, so silently deleting it because an instructor
             | left would destroy a question that students have already answered.
             |
             | The cost is that an instructor who authored work cannot be deleted
             | until it is reassigned or withdrawn. That is the correct direction to
             | fail in, and it is the same trade the announcements table already
             | makes for the same reason.
             */
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('title');

            // The instructions, in the instructor's own words.
            $table->text('instructions');

            // Optional: a Google Form for the work, and a PDF for the brief.
            // Both are links to somewhere, not uploads, because the work itself
            // is what gets uploaded by the student.
            $table->string('form_url')->nullable();
            $table->string('briefing_disk')->nullable();
            $table->string('briefing_path')->nullable();
            $table->string('briefing_mime_type')->nullable();
            $table->unsignedBigInteger('briefing_byte_size')->nullable();

            // The instructor's own scale. Null means "unmarked" and nothing else.
            $table->unsignedInteger('max_score')->nullable();

            $table->enum('status', ['draft', 'published', 'closed'])
                ->default('draft')
                ->index();

            $table->timestamp('available_from')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            // One assignment per title per lesson. A duplicate is nearly always a
            // double submission rather than two different briefs.
            $table->unique(['lesson_id', 'title']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();

            // Cascade: a deleted lesson takes its work with it, because the work
            // answers a question that no longer exists.
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();

            /*
             | Cascade on the student, and this one is the opposite trade to
             | `created_by` above.
             |
             | `created_by` restricts because deleting an instructor should not
             | delete a brief. `student_id` cascades because a removed student's
             | record of their own work is their data to take with them, and a row
             | left behind would be a student's answer attached to nobody.
             */
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();

            // The work. A generated path on the private disk, never the name the
            // student typed. See the note above.
            $table->string('storage_disk')->default('local');
            $table->string('storage_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('byte_size');

            /*
             | pending  submitted, waiting for checking. What the student sees.
             | graded   a mark and feedback exist.
             | returned the instructor handed it back to be redone. The student may
             |          submit again, and doing so returns the row to pending, which
             |          is the whole reason a returned submission is a state and not
             |          a message.
             */
            $table->enum('status', ['pending', 'graded', 'returned'])
                ->default('pending')
                ->index();

            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('graded_at')->nullable();

            $table->timestamps();

            // One submission per student per assignment. Re-uploading updates
            // the row rather than filling the table with drafts.
            $table->unique(['assignment_id', 'student_id']);

            // The queue an instructor works through: what is waiting, oldest first.
            $table->index(['assignment_id', 'status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
    }
};
