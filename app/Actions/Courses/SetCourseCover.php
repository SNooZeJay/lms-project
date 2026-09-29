<?php

namespace App\Actions\Courses;

use App\Models\Course;
use App\Models\User;
use App\Services\Storage\CourseCoverStorage;
use App\Support\CourseCoverCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Puts a cover on a course, takes one off, or leaves it alone.
 *
 * ONE ACTION FOR ALL THREE, RATHER THAN THREE METHODS
 *
 * Choosing a photograph, uploading a file and removing a cover are the same decision
 * in three forms, and they write the same six columns. Three methods would mean three
 * places to keep those six in step, and the one nobody remembered to update is how a
 * course ends up with a path and no source, or a source of "upload" pointing at a
 * photograph that was never written to the disk. One method, one place that writes,
 * one place that clears.
 *
 * THE DISK IS ONLY TOUCHED FOR AN UPLOAD
 *
 * A chosen photograph has no file here, and removing one deletes nothing. Both are
 * the storage service's business rather than this action's, and it refuses both cases
 * on the source column rather than on a guess about the path, so a photograph can
 * never cause a delete against the disk.
 *
 * THE PREVIOUS FILE IS DELETED AFTER THE ROW IS WRITTEN
 *
 * In that order, and outside the transaction. If the row is written first and the
 * delete fails, the course has a cover that works and one orphaned file. The other
 * order leaves a course pointing at a file that has already been removed, which shows
 * a placeholder for a cover the instructor chose and is worse than a wasted kilobyte.
 */
class SetCourseCover
{
    public function __construct(
        private readonly CourseCoverStorage $storage,
    ) {}

    /**
     * The storage service speaks in "path", the column is called thumbnail_path.
     *
     | The service returns what it stored rather than what to write, because a caller
     | that wanted a path and the row that holds it are two different things. That is
     | only safe while the translation lives in one place, so it lives here: one
     | method, and every write of a cover goes through it. The first version passed
     | the service's own array straight to forceFill, and the database refused a
     | column called "path" that has never existed.
     |
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function coverColumnsFrom(array $stored): array
    {
        return [
            'thumbnail_path' => $stored['path'] ?? null,
            'cover_disk' => $stored['disk'] ?? null,
            'cover_mime_type' => $stored['mime_type'] ?? null,
            'cover_byte_size' => $stored['byte_size'] ?? null,
            'cover_source' => $stored['source'] ?? null,
            'cover_credit_name' => $stored['credit_name'] ?? null,
            'cover_credit_url' => $stored['credit_url'] ?? null,
        ];
    }

    /**
     * Choose from the catalog. Nothing is written to the disk.
     */
    public function choosePhotograph(User $actor, Course $course, string $identifier): Course
    {
        Gate::forUser($actor)->authorize('update', $course);

        /*
         | Checked here as well as in the request.
         |
         | The request validates the identifier, and a request is not the only caller
         | and not a trusted one. An identifier that is not in the catalog is not a
         | choice anybody was offered, and storing it would put a string in the path
         | column that nothing can render and nothing can credit.
         */
        if (! CourseCoverCatalog::offers($identifier)) {
            throw new \InvalidArgumentException('That photograph is not one of the covers on offer.');
        }

        $previous = $course->only(['thumbnail_path', 'cover_source', 'cover_disk']);

        $course->forceFill($this->coverColumnsFrom($this->storage->forChosenPhotograph($identifier)))->save();

        // An upload that was replaced by a photograph is now an orphaned file, and
        // this is the only moment at which that is still knowable.
        if ($previous['cover_source'] === 'upload' && $previous['thumbnail_path'] !== null) {
            $this->storage->delete($course->forceFill([
                'thumbnail_path' => $previous['thumbnail_path'],
                'cover_source' => $previous['cover_source'],
                'cover_disk' => $previous['cover_disk'],
            ]));
        }

        return $course->refresh();
    }

    /**
     * Store an upload on the course's own disk.
     */
    public function upload(User $actor, Course $course, UploadedFile $file): Course
    {
        Gate::forUser($actor)->authorize('update', $course);

        $previousPath = $course->thumbnail_path;
        $previousSource = $course->cover_source;
        $previousDisk = $course->cover_disk;

        $stored = $this->storage->store($file, $course);

        DB::transaction(function () use ($course, $stored): void {
            $course->forceFill([
                'thumbnail_path' => $stored['path'],
                'cover_disk' => $stored['disk'],
                'cover_mime_type' => $stored['mime_type'],
                'cover_byte_size' => $stored['byte_size'],
                'cover_source' => $stored['source'],
                'cover_credit_name' => null,
                'cover_credit_url' => null,
            ])->save();
        });

        // The file is written before the row, so a failure here leaves an orphaned
        // file and a course with its previous cover, which is the recoverable way
        // round. The reverse would leave a course whose cover has been deleted.
        if ($previousPath !== null && $previousSource === 'upload' && $previousPath !== $stored['path']) {
            $this->storage->delete($course->forceFill([
                'thumbnail_path' => $previousPath,
                'cover_source' => $previousSource,
                'cover_disk' => $previousDisk,
            ]));
        }

        return $course->refresh();
    }

    /**
     * Take the cover off, leaving the course with a placeholder.
     *
     * The columns are cleared rather than the row being touched, so a course with no
     * cover and a course whose cover went missing are the same thing to every reader,
     * which is what makes the placeholder path a state rather than an accident.
     */
    public function remove(User $actor, Course $course): Course
    {
        Gate::forUser($actor)->authorize('update', $course);

        $previous = $course->only(['thumbnail_path', 'cover_source', 'cover_disk']);

        DB::transaction(function () use ($course): void {
            $course->forceFill($this->coverColumnsFrom($this->storage->forNoCover()))->save();
        });

        if ($previous['cover_source'] === 'upload' && $previous['thumbnail_path'] !== null) {
            $this->storage->delete($course->forceFill([
                'thumbnail_path' => $previous['thumbnail_path'],
                'cover_source' => $previous['cover_source'],
                'cover_disk' => $previous['cover_disk'],
            ]));
        }

        return $course->refresh();
    }
}
