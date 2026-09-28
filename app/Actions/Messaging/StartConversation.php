<?php

namespace App\Actions\Messaging;

use App\Enums\ConversationKind;
use App\Enums\ConversationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Course;
use App\Models\User;
use App\Policies\ConversationPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Opening a thread.
 *
 * There is exactly one course thread per student and instructor per course, and
 * that is a property of the unique index on (kind, course_id, requester_id)
 * rather than of a check. Two people who both press the button at the same
 * moment race here, one wins, and the other is handed the thread the winner
 * created instead of opening a second one.
 *
 * Both participants are added here rather than by the caller, because a thread
 * with one person in it is a thread nobody can reply to.
 */
class StartConversation
{
    /**
     * Open the one thread between a student and an instructor about a course,
     * or hand back the one that already exists.
     */
    public function startCourseThread(User $requester, User $counterpart): Conversation
    {
        /*
         | The course is resolved before the check rather than passed in, because
         | the rule judges the pair and the course together: a student may open a
         | thread with the instructor of a course they are enrolled in, and with
         | nobody else.
         */
        $course = $this->sharedCourse($requester, $counterpart);

        if ($course === null) {
            throw ValidationException::withMessages([
                'course' => 'You share no course with this person, so there is nobody here to message.',
            ]);
        }

        // The policy is asked directly rather than through Gate. These two
        // abilities have no single model to bind to, since the question is about
        // a pair of people and a course at once, and a Gate string would have to
        // guess which one. The model-bound abilities, view, reply and close, all
        // go through Gate as usual.
        if (! app(ConversationPolicy::class)->startCourseThread($requester, $counterpart, $course->id)) {
            throw ValidationException::withMessages([
                'course' => 'You cannot open a conversation with this person about this course.',
            ]);
        }

        return $this->open(ConversationKind::Course, $requester, $counterpart, $course->id);
    }

    /**
     * Open the one thread between two people about a course, or make it.
     */
    public function open(ConversationKind $kind, User $requester, ?User $counterpart, ?int $courseId): Conversation
    {
        $threadKey = $this->threadKey($kind, $requester, $counterpart);

        try {
            return DB::transaction(function () use ($kind, $requester, $counterpart, $courseId, $threadKey): Conversation {
                $conversation = new Conversation;
                $conversation->forceFill([
                    'kind' => $kind,
                    'course_id' => $courseId,
                    'subject' => null,
                    'requester_id' => $requester->id,
                    'status' => ConversationStatus::Open,
                    'last_message_at' => null,
                    'thread_key' => $threadKey,
                ]);
                $conversation->save();

                $this->addParticipant($conversation, $requester);

                if ($counterpart !== null) {
                    $this->addParticipant($conversation, $counterpart);
                }

                return $conversation;
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateThread($e)) {
                /*
                 | Somebody opened this exact thread a moment ago. Theirs is the
                 | thread, and this is the row to read.
                 |
                 | The lookup is on the same key the unique index refused, so it
                 | cannot return a different thread than the one that already
                 | existed. The previous version searched by requester and
                 | course, which is not what the index is keyed on and so could
                 | hand back nothing and rethrow.
                 */
                $existing = Conversation::query()
                    ->where('kind', $kind->value)
                    ->where('thread_key', $threadKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    public function startSupportThread(User $requester, string $subject): Conversation
    {
        // Asked directly, for the same reason as the course thread above: there
        // is no model for this ability to hang off.
        if (! app(ConversationPolicy::class)->startSupportThread($requester)) {
            throw ValidationException::withMessages([
                'support' => 'Your account cannot raise a support request.',
            ]);
        }

        $conversation = $this->open(ConversationKind::Support, $requester, null, null);

        $conversation->forceFill(['subject' => $subject])->save();

        return $conversation;
    }

    private function addParticipant(Conversation $conversation, User $user): void
    {
        $participant = new ConversationParticipant;
        $participant->forceFill([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'last_read_at' => null,
            'last_read_message_id' => null,
            'archived_at' => null,
        ]);
        $participant->save();
    }

    /**
     * A course the two of them genuinely share.
     *
     * Resolved through the counterpart's courses and the requester's
     * enrollment, so a thread can only be opened for a relationship that already
     * exists. A student who is not enrolled has no course in common with an
     * instructor and gets nothing back.
     */
    /**
     * The deterministic identity of a thread.
     *
     | A course thread is identified by its pair, written in a fixed order, so
     * the string does not depend on who opened it. Sorting the two ids is what
     * makes "the same pair" something the database can see: without it the
     | student and the instructor write different keys for one conversation and
     | the unique index happily stores both.
     |
     | A support thread is the opposite case. There is no pair to identify,
     | because every raise is a new conversation: somebody who reports a broken
     | course page and then a broken upload has two problems, and collapsing
     | them would bury the first one. The first version keyed it on the
     | requester, which did exactly that. So it gets a fresh identifier and the
     | index never has anything to refuse, which is the correct outcome rather
     | than a gap in the design.
     */
    private function threadKey(ConversationKind $kind, User $requester, ?User $counterpart): string
    {
        if ($kind === ConversationKind::Support) {
            return 'support:'.Str::uuid()->toString();
        }

        $ids = [$requester->id, $counterpart?->id ?? 0];
        sort($ids, SORT_NUMERIC);

        return 'course:'.$ids[0].'-'.$ids[1];
    }

    /**
     * A course the two of them genuinely share.
     *
     * Resolved by role rather than by "whoever asked", because a student may
     * open a thread with the instructor of a course they are enrolled in and an
     * instructor may open one with a student of their own course, and in the
     * second case the counterpart is the student. The first version asked for a
     * course taught by the counterpart, which silently returned nothing when an
     * instructor was the one reaching out.
     *
     * The status list is the same one StudentCourseAccess uses, so a cancelled
     * enrollment closes the thread as well as the course.
     */
    private function sharedCourse(User $requester, User $counterpart): ?Course
    {
        [$student, $instructor] = match ($requester->profile?->role) {
            UserRole::Student => [$requester, $counterpart],
            UserRole::Instructor => [$counterpart, $requester],
            // Not a pair that can share a course thread. The Policy refuses
            // these anyway, and refusing here means the message says the pair is
            // wrong rather than that no course happens to be shared.
            default => [null, null],
        };

        if ($student === null || $instructor === null) {
            return null;
        }

        return Course::query()
            ->where('instructor_id', $instructor->id)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('student_id', $student->id)
                ->whereIn('status', [
                    EnrollmentStatus::Active,
                    EnrollmentStatus::Completed,
                ]))
            ->first();
    }

    private function isDuplicateThread(QueryException $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'unique')
            && str_contains($e->getMessage(), 'conversations_deterministic_thread_unique');
    }
}
