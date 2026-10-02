<?php

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Who may set, release, and see an assignment.
 *
 * The rule in one sentence: an Instructor may act on work belonging to a course
 * they own, and nobody else may act on anything.
 *
 * Ownership is resolved through the lesson, the module and the course rather
 * than being stored on the assignment, so it cannot disagree with who owns the
 * course. An assignment copied between courses would keep the course's owner,
 * which is the answer that matters.
 */
class AssignmentPolicy
{
    /**
     * May this instructor set work on this course?
     *
     * The only "create" in the application that is not about the student's own
     * course, which is why it exists.
     */
    public function create(User $user, Course $course): bool
    {
        return $course->instructor_id === $user->id;
    }

    /**
     * May this instructor see or change this assignment?
     */
    public function view(User $user, Assignment $assignment): bool
    {
        return $assignment->lesson->module->course->instructor_id === $user->id;
    }

    /**
     * May this instructor change it?
     *
     * A closed assignment is not editable. Once work has been handed in against
     * it, changing the brief would make the answers describe a question that was
     * never asked.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        return $assignment->lesson->module->course->instructor_id === $user->id
            && $assignment->status !== Assignment::CLOSED;
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $assignment->lesson->module->course->instructor_id === $user->id
            && $assignment->submissions()->count() === 0;
    }

    /**
     * May this student read this assignment?
     *
     * Once it has been released, and not before. A draft is the instructor's own
     * note to themselves.
     *
     * CLOSED IS STILL READABLE, and that is the part worth being explicit about.
     * Closing stops new work being accepted; it does not make the brief vanish
     * from a student who was already given it, because that student still needs
     * to read the question they answered and see the mark they were given. A
     * policy of isPublished() alone makes closing a brief indistinguishable
     * from withdrawing it, and a 403 on a page a student already read is a bug
     * dressed as a security control.
     *
     * Whether the work may be handed in is a different question, and it is asked
     * in the controller where the answer is 410 rather than 403: the address
     * exists, the brief exists, and this one is finished.
     */
    public function readForStudent(User $user, Assignment $assignment): bool
    {
        return $assignment->status !== Assignment::DRAFT;
    }

    /**
     * May this instructor record a mark on this hand-in?
     *
     * Separate from
iew because reading work and writing to a student's
     * record are different permissions, and a policy that treats them as one
     * means anybody who can see a queue can also write to every student's record
     * in it.
     *
     * Handed-back work can be marked. The instructor who returned it has not
     * finished with it, and forbidding a mark on returned work would mean the
     * only way to finish was to re-open it, which nobody asked for.
     */
    /**
     * May this instructor read one hand-in?
     *
     * A separate name from
iew rather than the same one, and the reason is
     * mechanical: one Policy class is registered for two models, so a
iew
     * method typed to Assignment is also reached when a Submission is passed
     * to it. That is a TypeError and a 500, not a 403 — a wrong answer arriving
     * as a server fault, which is worse than no answer because it looks like a
     * bug in the thing being protected rather than a rule refusing the request.
     *
     * The rule itself is course ownership, the same as everything else here.
     */
    public function viewSubmission(User $user, AssignmentSubmission $submission): bool
    {
        return $submission->assignment->lesson->module->course->instructor_id === $user->id;
    }

    public function grade(User $user, AssignmentSubmission $submission): bool
    {
        return $submission->assignment->lesson->module->course->instructor_id === $user->id;
    }

    /**
     * May this instructor download the file behind this hand-in?
     *
     * Owns the course. No enrolment test: an instructor does not have to be
     * enrolled in their own course to read what was handed in against it.
     */
    public function downloadSubmission(User $user, AssignmentSubmission $submission): bool
    {
        return $submission->assignment->lesson->module->course->instructor_id === $user->id;
    }

    /**
     * May this student download their own hand-in?
     *
     * Their own row, matched against the authenticated user rather than
     * accepted from the route.
     */
    public function downloadOwnSubmission(User $user, AssignmentSubmission $submission): bool
    {
        return $submission->student_id === $user->id;
    }

    /**
     * May this person download the briefing attached to this assignment?
     *
     * Two relationships answer to one ability, because the file is the same file
     * and the two questions are genuinely different. An instructor asks "did I
     * write this", answered by course ownership, and works even while the brief
     * is a draft. A student asks "may I read this brief", answered by the
     * assignment being published and by having an enrollment that grants access,
     * and does not work on a draft at all.
     *
     * The enrollment is a query rather than a method on the user because the
     * states that grant access are two, not one, and a relation that returned
     * every enrollment would make each caller re-apply the rule. Getting this
     * wrong in the permissive direction opens every enrolled student to every
     * brief, so it is written out longhand.
     */
    public function downloadBriefing(User $user, Assignment $assignment): bool
    {
        $course = $assignment->lesson->module->course;

        if ($course->instructor_id === $user->id) {
            return true;
        }

        return $assignment->isPublished()
            && Enrollment::query()
                ->where('student_id', $user->id)
                ->where('course_id', $course->id)
                ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
                ->exists();
    }
}
