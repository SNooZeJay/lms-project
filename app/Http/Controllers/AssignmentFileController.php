<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Services\Assignments\AssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every assignment file leaves through here.
 *
 * No route in this application serves a storage path, and there is no URL that
 * takes a filename. Both of those are one missing line away from letting anybody
 * with a browser read every student's work in the school, so the path is only
 * ever taken from a record that was looked up by primary key, and the ability is
 * always checked before the disk is touched.
 *
 * THREE FILES, THREE ANSWERS TO "MAY I"
 *
 *   a briefing     the instructor who set it, or a student with access to the
 *                  course. Two abilities, because they are two different
 *                  relationships and a policy that flattened them would either
 *                  lock instructors out of their own briefs or open every
 *                  enrolled student's briefs to every other student.
 *
 *   a hand-in      the student who wrote it, or the instructor who owns the
 *                  course. Nobody else, including administrators, and that is
 *                  deliberate: an administrator can see that work exists and who
 *                  wrote it, but marking happens between an instructor and a
 *                  student and no one else has a place in it.
 *
 * THE HEADERS ARE NOT DECORATION
 *
 * `nosniff` because the media type comes from what the server recorded when the
 * file arrived, and a browser that is told to guess will guess `text/html` for a
 * file with no extension. `Content-Disposition: attachment` because nothing
 * anybody uploads here should ever be rendered in the application's own origin —
 * a PDF opened inline on the same origin as the session cookie is a PDF that
 * shares a session with the application.
 *
 * `private, no-store` because a student's answer cached by a shared machine's
 * browser is a student's answer visible to the next person at that machine.
 */
class AssignmentFileController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments) {}

    /**
     * The brief attached to an assignment.
     */
    public function briefing(Request $request, Assignment $assignment): Response
    {
        abort_unless($assignment->briefing_path !== null && $assignment->briefing_path !== '', 404);

        Gate::authorize('downloadBriefing', $assignment);

        return $this->serve(
            (string) $assignment->briefing_disk,
            (string) $assignment->briefing_path,
            $assignment->briefing_mime_type,
            $this->safeFilename((string) $assignment->title, 'briefing'),
        );
    }

    /**
     * A student's hand-in, read by the instructor who owns the course.
     */
    public function submission(Request $request, AssignmentSubmission $submission): Response
    {
        Gate::authorize('downloadSubmission', $submission);

        return $this->serve(
            $submission->storage_disk,
            $submission->storage_path,
            $submission->mime_type,
            $this->safeFilename($submission->original_name, 'submission'),
        );
    }

    /**
     * A student's own hand-in, read back by that student.
     *
     * A separate route rather than a branch inside the one above, because the
     * two answers come from two different policies. Folding them into one method
     * would mean one `Gate::authorize` call deciding between them, and the only
     * way to write that is an `if` on the user's role, which is the thing Policies
     * exist to stop.
     */
    public function ownSubmission(Request $request, AssignmentSubmission $submission): Response
    {
        Gate::authorize('downloadOwnSubmission', $submission);

        return $this->serve(
            $submission->storage_disk,
            $submission->storage_path,
            $submission->mime_type,
            $this->safeFilename($submission->original_name, 'submission'),
        );
    }

    /**
     * Read the bytes and hand them over, or admit they are not there.
     *
     * A missing file is a 404 rather than an empty 200, because an empty
     * download that reports success is a failure an instructor will believe is a
     * mark of zero.
     */
    private function serve(
        string $disk,
        string $path,
        ?string $mimeType,
        string $filename,
    ): Response {
        $contents = $this->assignments->contents($disk, $path);

        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => $mimeType ?? 'application/octet-stream',
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * A name that is safe to put in a header.
     *
     * Two problems, both solved the same way. A name may contain a quote, a
     * newline or a slash, and any of those in a `Content-Disposition` header lets
     * the sender choose part of the response header. And the student's own
     * filename is not ours to echo back verbatim, so the extension is kept and
     * the stem is replaced.
     *
     * ASCII only, because the header is a byte string and this value goes into
     * it unencoded.
     */
    private function safeFilename(string $original, string $fallbackStem): string
    {
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

        $stem = $fallbackStem;

        // Only the extension is honoured, and only if it is a short lowercase
        // string of letters and digits. Anything else is dropped rather than
        // escaped, because there is nothing here worth escaping.
        return $extension === '' ? $stem : $stem.'.'.$extension;
    }
}
