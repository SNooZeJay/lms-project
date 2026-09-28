<?php

namespace App\Enums;

/**
 * Every kind of notice this application can raise.
 *
 * The source specification listed twenty two names. Twenty one are unique,
 * because LESSON_COMPLETED appears twice in it. Six of those cannot be produced
 * by this application and are deliberately absent rather than reserved:
 *
 *   COURSE_ACTIVITY            a category, not an event. It has no destination
 *                              and no icon, so it could not be rendered honestly.
 *   ASSIGNMENT_SUBMITTED       there is no assignment domain. See
 *   ASSIGNMENT_GRADED          docs/plan.md section 0.2.
 *   MODULE_COMPLETED           completion is a course level state. There is no
 *                              module completion to announce.
 *   SYSTEM_MAINTENANCE         needs scheduled publishing, which was deferred.
 *                              See docs/plan.md section 0.3.
 *
 * CERTIFICATE_ELIGIBLE is absent for a different reason. The certificate is
 * issued in the same transaction that completes the course, so eligibility is
 * never a separately observable state. A notice about it would describe
 * something that has not happened yet. CERTIFICATE_AVAILABLE is the type that
 * matches what actually occurs.
 *
 * Two are added, because this application can produce them and the
 * specification calls its own list illustrative rather than closed:
 * CERTIFICATE_REVOKED, because a withdrawn certificate is something the student
 * who earned it is entitled to know about, and CERTIFICATE_REISSUED, so a
 * replacement can be found and the old link can explain itself.
 *
 * Fifteen kept, two added, seventeen in total. Every value here has at least one
 * caller in the automation matrix; a value with no caller is dead weight that
 * later looks like a feature.
 */
enum NotificationType: string
{
    case CourseEnrollment = 'course_enrollment';
    case CourseContentPublished = 'course_content_published';
    case CourseCompleted = 'course_completed';

    case LessonStarted = 'lesson_started';
    case LessonCompleted = 'lesson_completed';

    case QuizStarted = 'quiz_started';
    case QuizCompleted = 'quiz_completed';
    case QuizPassed = 'quiz_passed';
    case QuizFailed = 'quiz_failed';
    case RetakeRequired = 'retake_required';

    case CertificateAvailable = 'certificate_available';
    case CertificateRevoked = 'certificate_revoked';
    case CertificateReissued = 'certificate_reissued';

    case Announcement = 'announcement';

    /*
     | Two message types, not one.
     |
     | The plan listed a single NEW_MESSAGE. That does not survive contact with
     | the data: a course thread has a course and a support thread has none, and
     | isCourseScoped() is a property of the type rather than of a row, precisely
     | so a caller cannot write a row that no filter can classify. One type would
     | have to be scoped or unscoped, and either choice makes the other kind of
     | thread unwritable.
     |
     | Splitting them is the smaller change. The vocabulary gains a word and the
     | seam gets to keep the rule that caught this.
     */
    case CourseMessage = 'course_message';
    case SupportMessage = 'support_message';

    case SupportReply = 'support_reply';
    case SystemAnnouncement = 'system_announcement';

    /**
     * Every value, for the migration that builds the database enum.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether a notice of this type is expected to belong to a course.
     *
     * A platform wide notice has no course. This is a property of the type
     * rather than of a particular row, so a caller cannot raise a platform
     * notice and then attach it to a course by mistake.
     *
     * SupportReply, SupportMessage and SystemAnnouncement are not course scoped,
     * and that is a correction. The first version scoped every type except the
     * two announcement ones, on the assumption that support was about a course.
     * It is not: a support request is raised by a person about the system, has no
     * course at all, and the seam refused to record the notices that a support
     * thread most needs. A rule that blocks the notification a person is waiting
     * for is worse than no rule.
     *
     * ANNOUNCEMENT stays course scoped, and that is the second correction. The
     * plan's automation matrix lists it as course scope, because a course
     * announcement belongs to a course and a platform one is SYSTEM_ANNOUNCEMENT.
     * Listing Announcement alongside SystemAnnouncement as platform scope was
     * wrong, and it was invisible until slice 11 tried to write one: the seam
     * refused to store the notice for a course announcement.
     */
    public function isCourseScoped(): bool
    {
        return match ($this) {
            self::SystemAnnouncement,
            self::SupportReply,
            self::SupportMessage => false,
            default => true,
        };
    }
}
