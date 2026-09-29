<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A course cover image, and where it came from.
 *
 * WHY A MIGRATION RATHER THAN A FIELD ALREADY THERE
 *
 * `thumbnail_path` has existed on the courses table since the beginning, nullable,
 * and never used. Both course requests prohibit it, so a request cannot write it, and
 * `CreateCourse` sets it to null. It was a column waiting for a decision rather than a
 * working field, and the decision is that one string is not enough to hold an image
 * safely.
 *
 * WHAT A PATH ALONE CANNOT ANSWER
 *
 * Where the file is, yes. But not whether it is a photograph, not how big it is, not
 * whether it is on this application's disk or somebody else's, and not whether the
 * thing being displayed came from an instructor's upload or from a third party whose
 * licence asks to be credited. Each of those is a question the interface has to be
 * able to answer on every page that shows a cover, and answering it by re-reading the
 * file on each request would put a disk read behind a course list.
 *
 * So the cover is stored the way a learning material file is stored: the path, the
 * disk, the MIME type read from the file, and the size. Those four travel together
 * because they describe one thing, and they are stored rather than derived because
 * the interface reads them on every card.
 *
 * WHY THE SOURCE AND THE CREDIT ARE COLUMNS
 *
 * A cover chosen from the curated Unsplash set is not this application's file. It is
 * a photograph by somebody else, and the terms under which it is served ask for the
 * photographer to be credited and for the address the photograph came from to be
 * kept. Both are stored beside the image rather than in a view, because the
 * attribution has to survive a re-theme, a new card component and an export, and a
 * credit assembled inside a Blade template is one somebody will forget to carry over.
 *
 * `cover_source` is an enum rather than a boolean because the two are genuinely
 * different states with different rules: an upload is the instructor's own file and
 * is ours to serve, while a chosen photograph is served from its own origin and
 * credited. A boolean would make "there is a cover" true for both and hide the
 * difference that decides how it is displayed.
 *
 * THE PATH IS INDEXED
 *
 * Not because anything looks a cover up by path, but because it is a nullable column
 * that a listing may filter on when it wants only the courses that have one, and an
 * unindexed nullable column of this shape is the one a future query reaches for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('cover_disk')->nullable()->after('thumbnail_path');
            $table->string('cover_mime_type')->nullable()->after('cover_disk');
            $table->unsignedBigInteger('cover_byte_size')->nullable()->after('cover_mime_type');
            $table->enum('cover_source', ['upload', 'unsplash'])->nullable()->after('cover_byte_size');
            $table->string('cover_credit_name')->nullable()->after('cover_source');
            $table->string('cover_credit_url')->nullable()->after('cover_credit_name');

            // On thumbnail_path, which is the column that already holds the path.
            // The index was written against a cover_path that this migration does
            // not create, because the path was assumed to be moving here rather
            // than filled in, and the migration then failed on the first run
            // against an empty database. Reusing the column that exists is the
            // smaller change: one place holds the path rather than two that have
            // to agree.
            $table->index('thumbnail_path', 'courses_thumbnail_path_index');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex('courses_thumbnail_path_index');

            $table->dropColumn([
                'cover_disk',
                'cover_mime_type',
                'cover_byte_size',
                'cover_source',
                'cover_credit_name',
                'cover_credit_url',
            ]);
        });
    }
};
