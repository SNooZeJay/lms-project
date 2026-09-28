<?php

namespace App\Support;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Certificate;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The workspace navigation, built from what the signed-in person may actually open.
 *
 * The design system requires role based navigation generated from Policies.
 * Each item below declares the ability that guards its destination, and the
 * caller keeps only the items the server already allows.
 *
 * These abilities decide what is drawn. They never replace a Policy check, and
 * they never protect a route. A hidden link is a convenience, not a control.
 */
class Navigation
{
    /**
     * The navigation groups for one user, already filtered by ability.
     *
     * @return list<array{label: string, items: list<array{label: string, route: string, icon: string, matches: list<string>}>}>
     */
    public static function for(User $user): array
    {
        $role = $user->profile?->role;
        $isActive = $user->profile?->account_status === UserAccountStatus::Active;

        // A suspended or unrecognised account keeps the shell but is given no
        // role navigation at all, so it never shows a link it cannot use.
        if (! $isActive || $role === null) {
            return [];
        }

        return match ($role) {
            UserRole::Student => self::student($user),
            UserRole::Instructor => self::instructor($user),
            UserRole::Administrator => self::administrator($user),
            default => [],
        };
    }

    /**
     * @return list<array{label: string, items: list<array{label: string, route: string, icon: string, matches: list<string>}>}>
     */
    private static function student(User $user): array
    {
        return self::keep([
            [
                'label' => 'Learning',
                'items' => [
                    self::item('Dashboard', 'student.dashboard', 'home', ['student.dashboard']),
                    self::item('My courses', 'student.courses.index', 'book-open', ['student.courses.*']),
                    self::item('Certificates', 'student.certificates.index', 'award', ['student.certificates.*']),
                ],
            ],
            [
                'label' => 'Account',
                'items' => [
                    // Announcements is here rather than in a group of its own
                    // because it is a place to go, not a subject to study, and
                    // every role has one: a student sees the platform notices and
                    // the courses they are in, an instructor the courses they
                    // teach, and an administrator everything.
                    //
                    // Available to every role, and not special cased: what a
                    // person may read is an enrollment question rather than a
                    // role one, and AnnouncementPolicy answers it.
                    self::item('Announcements', 'announcements.index', 'megaphone', ['announcements.*']),
                    // Messages sits under Account rather than in a group of its
                    // own. It is a place to go, not a subject to study, and the
                    // topbar already carries the count, so the sidebar only
                    // needs the destination.
                    //
                    // Every role gets it, and none of them is special cased:
                    // who is inside a thread is decided by the participant
                    // table, not by which role is signed in, so an
                    // administrator and a student reach the same entry and see
                    // different things behind it.
                    self::item('Messages', 'conversations.index', 'message-square', ['conversations.*']),
                    self::item('Profile', 'account.profile', 'user', ['account.profile']),
                    self::item('Password', 'account.password', 'lock', ['account.password']),
                ],
            ],
        ], $user);
    }

    /**
     * @return list<array{label: string, items: list<array{label: string, route: string, icon: string, matches: list<string>}>}>
     */
    private static function instructor(User $user): array
    {
        return self::keep([
            [
                'label' => 'Teaching',
                'items' => [
                    self::item('Dashboard', 'instructor.dashboard', 'home', ['instructor.dashboard']),
                    self::item('My courses', 'instructor.courses.index', 'book-open', [
                        'instructor.courses.index',
                        'instructor.courses.show',
                        'instructor.courses.create',
                        'instructor.courses.edit',
                        'instructor.courses.*',
                    ]),
                ],
            ],
            [
                'label' => 'Account',
                'items' => [
                    // Announcements is here rather than in a group of its own
                    // because it is a place to go, not a subject to study, and
                    // every role has one: a student sees the platform notices and
                    // the courses they are in, an instructor the courses they
                    // teach, and an administrator everything.
                    //
                    // Available to every role, and not special cased: what a
                    // person may read is an enrollment question rather than a
                    // role one, and AnnouncementPolicy answers it.
                    self::item('Announcements', 'announcements.index', 'megaphone', ['announcements.*']),
                    // Messages sits under Account rather than in a group of its
                    // own. It is a place to go, not a subject to study, and the
                    // topbar already carries the count, so the sidebar only
                    // needs the destination.
                    //
                    // Every role gets it, and none of them is special cased:
                    // who is inside a thread is decided by the participant
                    // table, not by which role is signed in, so an
                    // administrator and a student reach the same entry and see
                    // different things behind it.
                    self::item('Messages', 'conversations.index', 'message-square', ['conversations.*']),
                    self::item('Profile', 'account.profile', 'user', ['account.profile']),
                    self::item('Password', 'account.password', 'lock', ['account.password']),
                ],
            ],
        ], $user);
    }

    /**
     * @return list<array{label: string, items: list<array{label: string, route: string, icon: string, matches: list<string>}>}>
     */
    private static function administrator(User $user): array
    {
        return self::keep([
            [
                'label' => 'Operations',
                'items' => [
                    self::item('Dashboard', 'administrator.dashboard', 'home', ['administrator.dashboard']),
                    self::item('Users', 'admin.users.index', 'users', ['admin.users.*']),
                    self::item('Certificates', 'admin.certificates.index', 'award', ['admin.certificates.*']),
                    self::item('Reports', 'admin.reports.index', 'chart', ['admin.reports.*']),
                    self::item('Activity log', 'admin.activity.index', 'activity', ['admin.activity.*']),
                    self::item('Support', 'admin.support.index', 'shield', ['admin.support.*']),
                ],
            ],
            [
                'label' => 'Account',
                'items' => [
                    // Announcements is here rather than in a group of its own
                    // because it is a place to go, not a subject to study, and
                    // every role has one: a student sees the platform notices and
                    // the courses they are in, an instructor the courses they
                    // teach, and an administrator everything.
                    //
                    // Available to every role, and not special cased: what a
                    // person may read is an enrollment question rather than a
                    // role one, and AnnouncementPolicy answers it.
                    self::item('Announcements', 'announcements.index', 'megaphone', ['announcements.*']),
                    // Messages sits under Account rather than in a group of its
                    // own. It is a place to go, not a subject to study, and the
                    // topbar already carries the count, so the sidebar only
                    // needs the destination.
                    //
                    // Every role gets it, and none of them is special cased:
                    // who is inside a thread is decided by the participant
                    // table, not by which role is signed in, so an
                    // administrator and a student reach the same entry and see
                    // different things behind it.
                    self::item('Messages', 'conversations.index', 'message-square', ['conversations.*']),
                    self::item('Profile', 'account.profile', 'user', ['account.profile']),
                    self::item('Password', 'account.password', 'lock', ['account.password']),
                ],
            ],
        ], $user);
    }

    /**
     * One navigation entry.
     *
     * `ability` overrides the route lookup when a page needs a different
     * ability than its neighbours, such as a read only report.
     *
     * @return array{label: string, route: string, icon: string, matches: list<string>, ability: array{0: string, 1: mixed}|null}
     */
    private static function item(string $label, string $route, string $icon, array $matches, ?array $ability = null): array
    {
        return [
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'matches' => $matches,
            'ability' => $ability,
        ];
    }

    /**
     * Drop any group whose items the user may not open.
     *
     * @param  list<array{label: string, items: list<array{label: string, route: string, icon: string, matches: list<string>, ability: array{0: string, 1: mixed}|null}>}>  $groups
     * @return list<array{label: string, items: list<array{label: string, route: string, icon: string, matches: list<string>}>}>
     */
    private static function keep(array $groups, User $user): array
    {
        $kept = [];

        foreach ($groups as $group) {
            $items = array_values(array_filter(
                $group['items'],
                fn (array $item): bool => self::isAllowed($user, $item)
            ));

            if ($items !== []) {
                $kept[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $kept;
    }

    /**
     * Ask the server whether this person may open the destination.
     *
     * @param  array{label: string, route: string, icon: string, matches: list<string>, ability: array{0: string, 1: mixed}|null}  $item
     */
    private static function isAllowed(User $user, array $item): bool
    {
        $ability = $item['ability'] ?? self::abilityFor($item['route']);

        if ($ability === null) {
            // A route with no registered Policy is still reachable, because the
            // route middleware is the authority for that area.
            return true;
        }

        [$name, $subject] = $ability;

        return Gate::forUser($user)->allows($name, $subject);
    }

    /**
     * The Policy ability that guards a named route, with the subject to test.
     *
     * The subject is an empty model instance, never `null`. Laravel resolves a
     * Policy from the model it is asked about, so a `null` subject would find no
     * Policy at all and every gated item would silently disappear from the
     * navigation. Nothing is saved and no record is read.
     *
     * @return array{0: string, 1: mixed}|null
     */
    private static function abilityFor(string $route): ?array
    {
        return match ($route) {
            'instructor.courses.index', 'instructor.courses.create' => ['create', new Course],
            'instructor.courses.show' => ['view', new Course],
            'admin.users.index' => ['viewAny', new User],
            'admin.certificates.index' => ['revoke', new Certificate],
            'admin.activity.index' => ['viewAny', new ActivityLog],
            'admin.support.index' => ['viewAnySupport', new Conversation],
            default => null,
        };
    }

    /**
     * The route name of the current page, used to highlight the active link.
     */
    public static function activeRoute(): string
    {
        return (string) request()->route()?->getName();
    }

    /**
     * Whether a navigation item is the one the reader is on.
     *
     * The `matches` list holds patterns, not literal route names. An item covers
     * its whole area with `admin.users.*`, so a sub page such as
     * `admin.users.index` has to be matched as a pattern.
     *
     * Comparing with in_array instead, which is exact, meant only the dashboard
     * ever matched, because its entry is a bare name with no wildcard. Every
     * other item stayed unhighlighted on every page underneath it, so a person
     * reading "Manage users" had no visible sign of where they were and a
     * screen reader was told nothing at all, because aria-current comes from
     * this same answer.
     *
     * @param  array{matches: list<string>}  $item
     */
    public static function isCurrent(array $item, ?string $current = null): bool
    {
        $current ??= self::activeRoute();

        if ($current === '') {
            return false;
        }

        foreach ($item['matches'] as $pattern) {
            if ($pattern === $current || Str::is($pattern, $current)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Where the current page sits, as a breadcrumb trail.
     *
     * Derived from the same data as the navigation rather than composed per
     * view. Twenty five views yielding their own trail array would be twenty five
     * places to forget one, and a trail that disagrees with the sidebar is worse
     * than no trail because it sends somebody looking for a page that is not
     * there.
     *
     * The root is the workspace name for the role, which is what the topbar used
     * to show as a flat string. That string is now the first crumb, so the
     * information is kept and given somewhere to lead.
     *
     * Only crumbs the server would actually allow are included. A trail is a
     * navigation, so it obeys the same rule as the sidebar: no link the person
     * cannot follow.
     *
     * @return list<array{label: string, href: string|null}>
     */
    public static function trail(User $user): array
    {
        $trail = [['label' => self::workspaceName($user), 'href' => self::dashboardFor($user)]];

        $current = self::activeRoute();

        if ($current === '' || $current === $trail[0]['href']) {
            // A dashboard is the root of its own workspace, so there is nowhere
            // above it to point. The page name is still added, because a trail
            // needs at least two steps to be a trail and the reference shows
            // "Workspace, Dashboard" rather than nothing.
            $trail[] = ['label' => self::pageLabel($current !== '' ? $current : 'student.dashboard'), 'href' => null];

            return $trail;
        }

        foreach (self::sectionsFor($user, $current) as $section) {
            $trail[] = $section;
        }

        // A page the navigation does not list, such as a form reached from a
        // list. The section it belongs to is still worth showing.
        $trail[] = ['label' => self::pageLabel($current), 'href' => null];

        return $trail;
    }

    /**
     * The section a page belongs to, with the section index as its own crumb.
     *
     * Only the workspace Dashboard is a section index. A group's first item was
     * used for this, which happens to be the Dashboard in the Learning, Teaching
     * and Operations groups and is Announcements in the Account group. That made
     * every Account page read "Learning workspace > Announcements > Profile", and
     * presented Messages as living under Announcements.
     *
     * Those are siblings in one navigation group, not a parent and a child, so
     * the trail was sending somebody to a page that does not contain the one
     * they were on. A wrong parent is worse than a short trail, because a short
     * one is merely less helpful and a wrong one is actively misleading.
     *
     * @return list<array{label: string, href: string|null}>
     */
    private static function sectionsFor(User $user, string $current): array
    {
        $dashboard = self::dashboardRouteFor($user);

        if ($dashboard === null) {
            return [];
        }

        foreach (self::for($user) as $group) {
            foreach ($group['items'] as $item) {
                if ($item['route'] !== $dashboard) {
                    continue;
                }

                // The page is the Dashboard itself, which is already the root of
                // the trail, so there is nothing between them to show.
                if ($current === $dashboard) {
                    return [];
                }

                return [[
                    'label' => $item['label'],
                    'href' => self::hrefFor($user, $item),
                ]];
            }
        }

        return [];
    }

    /**
     * A href only when the route exists and the person may open it.
     *
     * @param  array{label: string, route: string, icon: string, matches: list<string>, ability: array{0: string, 1: mixed}|null}  $item
     */
    private static function hrefFor(User $user, array $item): ?string
    {
        if (! self::isAllowed($user, $item)) {
            return null;
        }

        return Route::has($item['route']) ? route($item['route']) : null;
    }

    /**
     * The name of the current page.
     *
     * A navigation item's own label is used only when the current route *is*
     * that item's route. The previous version used the label of whichever item
     * matched the current route, and the Instructor sidebar has one item,
     * "My courses", whose match list covers `instructor.courses.*`. Every page
     * underneath it therefore ended in the same two words: the outline, the edit
     * form, the module editor, the lesson editor and the learner list.
     *
     * The last crumb carries `aria-current="page"`, so this is not only what a
     * person reads. A screen reader was told the reader was on "My courses"
     * whatever page they were actually on, which is worse than saying nothing.
     *
     * A sub page therefore names itself. The readable names are listed rather
     * than derived, because a route name headlined into words produces things
     * like "Attempts Show" and "Materials Edit", which is how a person finds out
     * that a string was split on a full stop. Anything not listed falls back to
     * the headlined route name, which is still better than naming the section.
     *
     * @return array<string, string>
     */
    private static function pageNames(): array
    {
        return [
            'instructor.courses.index' => 'Courses',
            'instructor.courses.create' => 'New course',
            'instructor.courses.show' => 'Outline',
            'instructor.courses.edit' => 'Edit course details',
            'instructor.courses.students' => 'Learners',
            'instructor.courses.modules.edit' => 'Modules',
            'instructor.courses.modules.lessons.edit' => 'Lessons',
            'instructor.courses.materials.edit' => 'Materials',
            'instructor.dashboard' => 'Dashboard',
            'instructor.courses' => 'My courses',
            'administrator.dashboard' => 'Dashboard',
            'admin.users.index' => 'Users',
            'admin.certificates.index' => 'Certificates',
            'admin.reports.index' => 'Reports',
            'admin.activity.index' => 'Activity log',
            'admin.support.index' => 'Support',
            'student.dashboard' => 'Dashboard',
            'student.courses.index' => 'My courses',
            'student.courses.show' => 'Course',
            'student.lessons.show' => 'Lesson',
            'student.quizzes.show' => 'Quiz',
            'student.quizzes.attempts.show' => 'Attempt',
            'student.quizzes.attempts.result' => 'Result',
            'student.certificates.index' => 'Certificates',
            'announcements.index' => 'Announcements',
            'announcements.show' => 'Announcement',
            'conversations.index' => 'Messages',
            'conversations.show' => 'Message',
            'support.create' => 'Ask for support',
            'account.profile' => 'Profile',
            'account.password' => 'Password',
            'notifications.index' => 'Notifications',
        ];
    }

    /**
     * The name of the current page.
     */
    private static function pageLabel(string $current): string
    {
        $named = self::pageNames()[$current] ?? null;

        if ($named !== null) {
            return $named;
        }

        /*
         | A route that is not listed and is not a navigation entry of its own.
         |
         | The navigation is consulted only for the exact route, never for a
         | pattern, because a pattern match is the section the page sits inside
         | rather than the page itself.
         */
        foreach (self::for(auth()->user()) as $group) {
            foreach ($group['items'] as $item) {
                if ($item['route'] === $current) {
                    return $item['label'];
                }
            }
        }

        $last = last(explode('.', $current));

        return Str::headline((string) $last);
    }

    /**
     * The word the role uses for its own area of the application.
     */
    private static function workspaceName(User $user): string
    {
        return match ($user->profile?->role) {
            UserRole::Instructor => 'Teaching workspace',
            UserRole::Administrator => 'Operations workspace',
            default => 'Learning workspace',
        };
    }

    /**
     * The route that opens this person's own area.
     */
    private static function dashboardFor(User $user): ?string
    {
        $name = self::dashboardRouteFor($user);

        return $name !== null && Route::has($name) ? route($name) : null;
    }

    /**
     * The route name of this person's own dashboard, or null for no role.
     *
     * Split from `dashboardFor` so the role mapping is written once. The
     * breadcrumb needs the name to recognise the Dashboard entry, and the trail
     * needs the address; deriving one from the other would mean the mapping
     * appears twice and the two copies can disagree.
     */
    private static function dashboardRouteFor(User $user): ?string
    {
        return match ($user->profile?->role) {
            UserRole::Student => 'student.dashboard',
            UserRole::Instructor => 'instructor.dashboard',
            UserRole::Administrator => 'administrator.dashboard',
            default => null,
        };
    }
}
