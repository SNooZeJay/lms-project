<?php

namespace App\Services\Storage;

use App\Models\Course;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores a course cover image on the public disk under a generated path.
 *
 * WHY THE PUBLIC DISK, WHEN LEARNING MATERIALS USE THE PRIVATE ONE
 *
 * A learning material file is the lesson's content, and a person who has not
 * enrolled has no business reading it. A course cover is the picture on a card in the
 * public catalog, on the home page, and on a search result, none of which anybody is
 * signed in to see. Serving it through an authorized download would mean a database
 * read and a policy check on every card of every list for an image that is already
 * public information, and would leave the one place a cover appears most, the
 * catalog, unable to show it at all.
 *
 * Nothing else is on this disk. Learning materials stay on the private one, and the
 * two are configured separately, so a cover cannot be used to reach a lesson file and
 * a lesson file is never reachable by guessing a path.
 *
 * WHY THE NAME IS GENERATED
 *
 * The path is a UUID under a directory named for the course, so an uploaded filename
 * is never used and cannot decide where anything lands. Two uploads of the same
 * original file land in two different places, and neither can overwrite the other.
 *
 * WHY THE MIME TYPE IS READ FROM THE FILE
 *
 * The browser sends a type, and the browser is describing what the person selected
 * rather than what they selected. The type stored is the one detected in the bytes,
 * and the extension written is the one belonging to that detected type, so the two
 * cannot disagree. The allow-list is a ceiling on the detected value rather than a
 * replacement for it, so a file that sniffs as something unexpected is refused by the
 * request rather than quietly stored under a name that hides it.
 */
class CourseCoverStorage
{
    public const DISK = 'public';

    /** Small enough for a card, large enough for a detail page header. */
    public const MAX_KILOBYTES = 2048;

    public const MIN_WIDTH = 320;

    public const MIN_HEIGHT = 180;

    /**
     * The types a cover may be, by detected MIME type.
     *
     * @return array<int, string>
     */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * The extension belonging to a detected type.
     *
     * @return array<string, string>
     */
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Store an upload and return the columns to write.
     *
     * @return array{path: string, disk: string, mime_type: string, byte_size: int, source: string}
     */
    public function store(UploadedFile $file, Course $course): array
    {
        /*
         | A cover cannot be stored for a course that has no id.
         |
         | The path is built from the course id, so an unsaved course writes into
         | "course-covers/0" and every unsaved course in the application shares that
         | directory. Nothing reaches this by accident: the route binds a saved
         | course, so the id is always set. It is refused here rather than left to
         | that, because a service that quietly files a file under zero is a service
         | that will file a lesson file under zero the first time somebody reuses it.
         */
        if (! $course->exists) {
            throw new \InvalidArgumentException('A cover cannot be stored before the course has been saved.');
        }

        $detected = $this->detectMimeType($file);

        $path = sprintf(
            'course-covers/%d/%s.%s',
            $course->id,
            Str::uuid()->toString(),
            self::EXTENSIONS[$detected] ?? 'jpg',
        );

        Storage::disk(self::DISK)->put($path, file_get_contents($file->getRealPath()));

        return [
            'path' => $path,
            'disk' => self::DISK,
            'mime_type' => $detected,
            'byte_size' => (int) $file->getSize(),
            'source' => 'upload',
        ];
    }

    /**
     * The address to serve an upload from.
     *
     * Read from the disk rather than composed from the path, so a disk configured
     * with a different root or a different host produces the right address instead of
     * one that 404s.
     */
    public function urlFor(Course $course, ?string $fallback = null): ?string
    {
        $path = $course->thumbnail_path;

        if ($path === null || $path === '') {
            return $fallback;
        }

        $disk = $course->cover_disk ?: self::DISK;

        if (! Storage::disk($disk)->exists($path)) {
            // The row points at a file that is not there, which happens if the disk
            // was cleared without the rows being cleared with it. A cover is not
            // worth a broken image on every card, so the placeholder is used and the
            // absence is not silent in the markup: the component marks it.
            return $fallback;
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * Remove a stored file, and only one that is ours.
     *
     * A path that did not come from this class is never deleted, so a cover chosen
     * from the catalog, which has no file here at all, cannot cause a delete against
     * the disk. That check is what keeps the two sources from sharing one column.
     */
    public function delete(Course $course): void
    {
        $path = $course->thumbnail_path;

        if ($path === null || $path === '') {
            return;
        }

        if ($course->cover_source !== 'upload') {
            return;
        }

        $disk = $course->cover_disk ?: self::DISK;

        if (! str_starts_with($path, 'course-covers/')) {
            return;
        }

        Storage::disk($disk)->delete($path);
    }

    /**
     * The columns for a photograph chosen from the catalog.
     *
     * Nothing is written to the disk: the photograph is served from Unsplash's own
     * address, which their guidance asks applications to do, and the identifier is
     * the path this application stores so the same photograph is chosen again for the
     * same course rather than a fresh one each time a page is built.
     *
     * THE CREDIT IS UNSPLASH, AND NOT THE PHOTOGRAPHER, AND THAT IS A LIMITATION
     *
     * Unsplash's guidance asks for the photographer to be credited by name, and this
     * cannot do that. A name is only knowable from the API, the API needs an Access
     * Key, and this application has none: every route to it answers 401 without one,
     * which was checked rather than assumed. Inventing twenty plausible names to fill
     * a column would put a false attribution next to a real person's photograph, and
     * a wrong credit is worse than an incomplete one.
     *
     * So the credit says Unsplash, which is the provider and is true, and points at
     * the source it came from. The two columns exist so that recording a real
     * photographer's name is a data change when a key exists, rather than a change to
     * every view that displays a cover. That is also why the columns are nullable:
     * an upload has no photographer and must not be given one.
     *
     * @return array{path: string, disk: null, mime_type: null, byte_size: null, source: string, credit_name: string, credit_url: string}
     */
    public function forChosenPhotograph(string $identifier): array
    {
        return [
            'path' => $identifier,
            'disk' => null,
            'mime_type' => null,
            'byte_size' => null,
            'source' => 'unsplash',
            'credit_name' => 'Unsplash',
            'credit_url' => 'https://unsplash.com',
        ];
    }

    /**
     * The columns for a course with no cover.
     *
     * @return array{path: null, disk: null, mime_type: null, byte_size: null, source: null, credit_name: null, credit_url: null}
     */
    public function forNoCover(): array
    {
        return [
            'path' => null,
            'disk' => null,
            'mime_type' => null,
            'byte_size' => null,
            'source' => null,
            'credit_name' => null,
            'credit_url' => null,
        ];
    }

    /**
     * The type the file actually is.
     *
     * Read from the bytes. A request can claim any type it likes, and the extension
     * on the name it claims can be anything too, so neither is evidence.
     */
    public function detectMimeType(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            return '';
        }

        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';
    }
}
