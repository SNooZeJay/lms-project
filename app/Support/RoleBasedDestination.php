<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

class RoleBasedDestination
{
    public static function for(User $user): string
    {
        return match ($user->profile?->role) {
            UserRole::Student => route('student.dashboard'),
            UserRole::Instructor => route('instructor.dashboard'),
            UserRole::Administrator => route('administrator.dashboard'),
            default => route('account.profile'),
        };
    }

    /**
     * Where this person's own courses are, and what to call that place.
     *
     * WHY THIS EXISTS
     *
     * Two places on the public pages offered "My courses" to anybody who was signed
     * in, pointing at `student.courses.index`. That route sits inside the group
     * guarded by the `role:student` middleware, so an instructor and an
     * administrator were each shown a link to a page that answers 403. One of the
     * two was the landing page, which is the most visible place a dead control can
     * be, and neither had a test, because a test that renders the page and looks
     * for the word "My courses" passes happily while the link is a dead end.
     *
     * The answer is per role, so it is written once here rather than guessed at in
     * each view. A second copy is how the two drifted apart in the first place.
     *
     * Returned as a pair rather than a bare URL because the label has to change
     * with the destination. An instructor does not have courses they are taking;
     * calling their list "My courses" the way a student's is called is the same
     * fault in a different place.
     *
     * A guest is answered here too, because a signed out visitor's first action is
     * to read the catalog, and a caller that has to remember that is a caller that
     * will eventually forget.
     *
     * @return array{route: string, label: string}
     */
    public static function learningFor(?User $user): array
    {
        if ($user === null) {
            return ['route' => 'courses.index', 'label' => 'Browse courses'];
        }

        return match ($user->profile?->role) {
            UserRole::Student => ['route' => 'student.courses.index', 'label' => 'My courses'],
            UserRole::Instructor => ['route' => 'instructor.courses.index', 'label' => 'My teaching courses'],
            UserRole::Administrator => ['route' => 'administrator.dashboard', 'label' => 'Operations workspace'],

            // Signed in, but carrying no role this application recognises, such as a
            // suspended account. The profile page is a real page for that person,
            // where a workspace route is not.
            default => ['route' => 'account.profile', 'label' => 'Your profile'],
        };
    }
}
