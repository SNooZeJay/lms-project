<?php

namespace App\Support;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

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
                ],
            ],
            [
                'label' => 'Account',
                'items' => [
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
}
