<?php

namespace App\Services\Assignments;

use App\Http\Requests\Instructor\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Setting work, and marking it.
 *
 * One class for both, because they are the same task seen from two sides: an
 * instructor writes something here, and an instructor reads it here. Splitting
 * them would put the rule about what a mark means in one file and the rule
 * about writing a mark in another, and those are the two that must agree.
 */
class AssignmentService
{
    /**
     * The private disk. Never `public`, and never a name from a request.
     */
    public const DISK = 'local';

    /**
     * The folder a briefing lives in.
     */
    private const BRIEFING_FOLDER = 'assignment-briefings';

    /**
     * The folder a hand-in lives in.
     */
    private const SUBMISSION_FOLDER = 'assignment-submissions';

    /**
     * Write an assignment against a lesson.
     */
    public function create(Course $course, Lesson $lesson, StoreAssignmentRequest $request): Assignment
    {
        return DB::transaction(function () use ($lesson, $request): Assignment {
            $briefing = $request->file('briefing');

            $assignment = Assignment::create([
                'lesson_id' => $lesson->id,
                'created_by' => $request->user()->id,
                'title' => $request->validated('title'),
                'instructions' => $request->validated('instructions'),
                'form_url' => $request->validated('form_url'),
                'max_score' => $request->validated('max_score'),
                'status' => $request->boolean('publish')
                    ? Assignment::PUBLISHED
                    : Assignment::DRAFT,
            ]);

            if ($briefing !== null) {
                $path = $briefing->store(self::BRIEFING_FOLDER, ['disk' => self::DISK]);

                $assignment->forceFill([
                    'briefing_disk' => self::DISK,
                    'briefing_path' => $path,
                    'briefing_mime_type' => $briefing->getMimeType(),
                    'briefing_byte_size' => $briefing->getSize(),
                ])->save();
            }

            return $assignment;
        });
    }

    /**
     * Publish a draft, or close one that has been released.
     *
     * Closing is not deleting. Submissions that exist are evidence that work
     * happened, and removing the brief would remove the question those answers
     * were written against.
     */
    public function changeStatus(Assignment $assignment, string $status): Assignment
    {
        $assignment->forceFill([
            'status' => $status,
            'closed_at' => $status === Assignment::CLOSED ? now() : null,
        ])->save();

        return $assignment;
    }

    /**
     * A student hands in their work.
     *
     * The file lands on the private disk under a generated path, and the name the
     * student gave it is kept only as a label. Re-uploading replaces the previous
     * file and its row, because a student has one submission per assignment and a
     * draft they meant to withdraw is not history worth keeping.
     */
    public function submit(Assignment $assignment, User $student, UploadedFile $file): AssignmentSubmission
    {
        return DB::transaction(function () use ($assignment, $student, $file): AssignmentSubmission {
            $existing = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->first();

            if ($existing !== null) {
                Storage::disk($existing->storage_disk)->delete($existing->storage_path);
            }

            $path = $file->store(self::SUBMISSION_FOLDER, ['disk' => self::DISK]);

            $values = [
                'storage_disk' => self::DISK,
                'storage_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'byte_size' => $file->getSize(),

                /*
                 | Handing it back to be redone means pending again.
                 |
                 | Not granted, and not returned: a student whose work was returned
                 | has work waiting for a second look, and the honest thing to show
                 | them is that it is in the queue again, not that a previous
                 | instructor's mark is still on screen.
                 */
                'status' => AssignmentSubmission::PENDING,
                'submitted_at' => now(),
                'score' => null,
                'feedback' => null,
                'graded_by' => null,
                'graded_at' => null,
            ];

            if ($existing !== null) {
                $existing->forceFill($values)->save();

                return $existing;
            }

            return AssignmentSubmission::create($values + [
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ]);
        });
    }

    /**
     * An instructor records a mark.
     *
     * The mark is checked against the assignment's own scale rather than trusted,
     * so a mark above the maximum is refused at the point it is written instead of
     * producing a number the interface cannot render.
     */
    public function grade(AssignmentSubmission $submission, User $grader, int $score, ?string $feedback): AssignmentSubmission
    {
        $assignment = $submission->assignment;

        if (! $assignment->isMarkable()) {
            throw new InvalidArgumentException('This assignment has no mark scale yet, so it cannot be marked.');
        }

        if ($score < 0 || $score > $assignment->max_score) {
            throw new InvalidArgumentException(
                "The mark must be between 0 and {$assignment->max_score}."
            );
        }

        $submission->forceFill([
            'score' => $score,
            'feedback' => $feedback,
            'status' => AssignmentSubmission::GRADED,
            'graded_by' => $grader->id,
            'graded_at' => now(),
        ])->save();

        return $submission;
    }

    /**
     * Hand the work back to be redone.
     *
     * Feedback is required here and nowhere else, because "try again" with no
     * reason is not feedback.
     */
    public function returnForRedo(AssignmentSubmission $submission, User $grader, string $feedback): AssignmentSubmission
    {
        $submission->forceFill([
            'status' => AssignmentSubmission::RETURNED,
            'feedback' => $feedback,
            'graded_by' => $grader->id,
            'graded_at' => null,
        ])->save();

        return $submission;
    }

    /**
     * Read a stored file back.
     *
     * Every download goes through here and through an authorisation check that
     * happens before this is called. There is no route anywhere that serves a
     * path straight off the disk.
     */
    public function contents(string $disk, string $path): ?string
    {
        return Storage::disk($disk)->exists($path)
            ? Storage::disk($disk)->get($path)
            : null;
    }
}
