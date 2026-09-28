<?php

namespace App\Policies;

use App\Enums\ConversationKind;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\User;
use App\Support\StudentCourseAccess;

/**
 * Who may read a thread, who may write in it, and who may close it.
 *
 * The rule is the participant table, not a role check. A person can see a thread
 * because they are in it, which is one query and one comparison, and it means
 * there is no arrangement of roles and courses for this policy to get wrong.
 *
 * Two answers in the plan's matrix are worth stating here because they are the
 * ones a policy like this usually gets wrong:
 *
 * - **An instructor cannot read a support thread.** No instructor is ever a
 *   participant in one, so the answer falls out of the rule rather than needing
 *   a special case. An instructor who teaches the person who raised it still
 *   cannot, which is the point.
 * - **An administrator cannot post into a course thread.** Administration is
 *   not teaching. They can read a course thread only if they are in it, and
 *   nothing puts them in one.
 */
class ConversationPolicy
{
    /**
     * Reading a thread, and seeing it listed.
     */
    public function view(User $actor, Conversation $conversation): bool
    {
        if (! $this->isActive($actor)) {
            return false;
        }

        return $this->isParticipant($actor, $conversation);
    }

    /**
     * Writing in a thread.
     *
     * A closed thread is read only. Closing is a promise, and a promise that
     * quietly stops being true is worse than no promise.
     */
    public function reply(User $actor, Conversation $conversation): bool
    {
        if (! $this->isActive($actor)) {
            return false;
        }

        if (! $conversation->isOpen()) {
            return false;
        }

        return $this->isParticipant($actor, $conversation);
    }

    /**
     * Starting a course thread.
     *
     * A student may open one for a course they are enrolled in. An instructor
     * may open one for a course they own, and the deterministic key means there
     * is only ever one per pair. An administrator may not: there is nobody on
     * the teaching side of a course thread for them to talk to.
     *
     * Asked directly by StartConversation rather than through Gate, because the
     * question is about a pair of people and a course at once and there is no
     * single model for the ability to hang off.
     */
    public function startCourseThread(User $actor, User $counterpart, int $courseId): bool
    {
        if (! $this->isActive($actor) || ! $this->isActive($counterpart)) {
            return false;
        }

        $course = Course::query()->find($courseId);

        if ($course === null) {
            return false;
        }

        if ($actor->profile?->role === UserRole::Student) {
            return $counterpart->profile?->role === UserRole::Instructor
                && $course->instructor_id === $counterpart->id
                && StudentCourseAccess::allows($actor, $course);
        }

        if ($actor->profile?->role === UserRole::Instructor) {
            return $counterpart->profile?->role === UserRole::Student
                && $course->instructor_id === $actor->id
                && StudentCourseAccess::allows($counterpart, $course);
        }

        return false;
    }

    /**
     * Raising a support thread.
     *
     * Any active account may ask for help. The thread belongs to whoever raised
     * it, which is what keeps it out of an instructor's reach.
     */
    public function startSupportThread(User $actor): bool
    {
        return $this->isActive($actor);
    }

    /**
     * Closing a support thread.
     *
     * Administration only. Closing a course thread is not offered at all, since
     * a course conversation has no state to settle.
     */
    public function close(User $actor, Conversation $conversation): bool
    {
        if (! $this->isActive($actor) || $actor->profile?->role !== UserRole::Administrator) {
            return false;
        }

        return $conversation->kind === ConversationKind::Support;
    }

    /**
     * Seeing the administration list of support threads.
     */
    public function viewAnySupport(User $actor): bool
    {
        return $this->isActive($actor) && $actor->profile?->role === UserRole::Administrator;
    }

    /**
     * Participation is the whole rule.
     */
    private function isParticipant(User $actor, Conversation $conversation): bool
    {
        return $conversation->participants()->where('user_id', $actor->id)->exists();
    }

    private function isActive(User $actor): bool
    {
        return $actor->profile?->account_status === UserAccountStatus::Active;
    }
}
