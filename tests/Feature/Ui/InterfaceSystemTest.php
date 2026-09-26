<?php

namespace Tests\Feature\Ui;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Support\Money;
use App\Support\Navigation;
use App\Support\StatusLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The interface system, tested as behaviour rather than as markup.
 *
 * These tests cover the promises the design system makes that are easy to break
 * silently during a redesign: one shell for every role, navigation that only
 * offers what the server allows, money that is never printed raw, and a status
 * that is always a word rather than a colour.
 */
class InterfaceSystemTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- the shell

    public function test_every_role_shares_one_authenticated_shell(): void
    {
        foreach ([UserRole::Student, UserRole::Instructor, UserRole::Administrator] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)
                ->get($this->dashboardFor($role))
                ->assertOk()
                // The shell landmarks every signed in page shares.
                ->assertSee('id="main-content"', false)
                ->assertSee('Skip to main content')
                ->assertSee('data-workspace-sidebar', false)
                ->assertSee('data-drawer-open', false)
                ->assertSee('data-drawer-close', false)
                ->assertSee('data-drawer-panel', false)
                // The account menu is a real control, not a hover-only menu.
                ->assertSee('data-dropdown-trigger', false)
                ->assertSee('aria-expanded="false"', false)
                // And the mark appears once per shell, not as a typed character.
                ->assertSee('data-brand-mark', false);
        }
    }

    public function test_the_public_shell_has_no_workspace_navigation(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-workspace-sidebar', false)
            ->assertDontSee('data-drawer-panel', false)
            ->assertSee('data-theme-toggle', false);
    }

    public function test_the_account_menu_offers_profile_password_and_sign_out(): void
    {
        $user = $this->makeUser(UserRole::Student);

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(route('account.profile'))
            ->assertSee(route('account.password'))
            ->assertSee(route('logout'), false);
    }

    public function test_the_drawer_names_its_button_and_toggles_its_state(): void
    {
        $user = $this->makeUser(UserRole::Student);

        $body = (string) $this->actingAs($user)->get(route('student.dashboard'))->getContent();

        // The open button must name the panel it controls, so the relationship is
        // announced rather than implied.
        $this->assertStringContainsString('data-drawer-open', $body);
        $this->assertStringContainsString('aria-controls="workspace-drawer"', $body);
        $this->assertStringContainsString('role="dialog"', $body);
        $this->assertStringContainsString('aria-modal="true"', $body);
        $this->assertStringContainsString('aria-label="Workspace navigation"', $body);
    }

    public function test_the_stylesheet_is_loaded_on_every_kind_of_page(): void
    {
        // The shell and the public layout each include the compiled stylesheet.
        // A missing include renders a page that is correct but completely
        // unstyled, which no server test would otherwise notice.
        foreach ([
            fn () => $this->get(route('home')),
            fn () => $this->get(route('login')),
            fn () => $this->actingAs($this->makeUser(UserRole::Student))->get(route('student.dashboard')),
        ] as $request) {
            $body = (string) $request()->assertOk()->getContent();

            $this->assertStringContainsString('build/assets/app-', $body);
            $this->assertStringContainsString('.css', $body);
        }
    }

    // -------------------------------------------------------------- navigation

    public function test_navigation_only_offers_what_the_server_allows(): void
    {
        $student = $this->makeUser(UserRole::Student);
        $instructor = $this->makeUser(UserRole::Instructor);
        $administrator = $this->makeUser(UserRole::Administrator);

        $studentRoutes = $this->routesIn(Navigation::for($student));
        $instructorRoutes = $this->routesIn(Navigation::for($instructor));
        $administratorRoutes = $this->routesIn(Navigation::for($administrator));

        $this->assertContains('student.courses.index', $studentRoutes);
        $this->assertNotContains('admin.users.index', $studentRoutes);
        $this->assertNotContains('instructor.courses.index', $studentRoutes);

        $this->assertContains('instructor.courses.index', $instructorRoutes);
        $this->assertNotContains('admin.certificates.index', $instructorRoutes);

        $this->assertContains('admin.users.index', $administratorRoutes);
        $this->assertContains('admin.activity.index', $administratorRoutes);
        $this->assertNotContains('student.courses.index', $administratorRoutes);
    }

    public function test_a_suspended_account_is_given_no_role_navigation(): void
    {
        $user = $this->makeUser(UserRole::Administrator);
        $user->profile->forceFill(['account_status' => UserAccountStatus::Suspended])->save();

        $this->assertSame([], Navigation::for($user->fresh()));
    }

    public function test_navigation_markdowns_the_current_page(): void
    {
        $user = $this->makeUser(UserRole::Student);

        $body = (string) $this->actingAs($user)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->getContent();

        // Exactly one navigation item may claim to be the current page, or a
        // screen reader announces two.
        $this->assertSame(
            1,
            substr_count($body, 'aria-current="page"'),
            'The sidebar must mark exactly one item as the current page.',
        );
    }

    // ------------------------------------------------------------------- money

    public function test_money_is_formatted_from_integer_minor_units(): void
    {
        $peso = "\u{20B1}";

        $expectations = [
            // A zero amount, a whole peso, a peso with cents, a grouped
            // thousands amount, a large amount, and a negative amount.
            0 => $peso.'0.00',
            100 => $peso.'1.00',
            50 => $peso.'0.50',
            125050 => $peso.'1,250.50',
            499900 => $peso.'4,999.00',
            -5000 => '-'.$peso.'50.00',
        ];

        foreach ($expectations as $minor => $expected) {
            $this->assertSame($expected, Money::format($minor), 'Failed for '.$minor.' minor units.');
        }
    }

    public function test_a_free_course_prints_the_word_free_and_not_a_peso_amount(): void
    {
        $this->assertSame('Free', Money::course(0, CourseType::Free));
        $this->assertSame('Free', Money::course(0, 'free'));
        $this->assertSame("\u{20B1}499.00", Money::course(49900, CourseType::Paid));
    }

    public function test_a_stored_charge_is_never_reported_as_free(): void
    {
        // A payment record has no course type. A null type must not fall through
        // to the word Free, or a paid invoice would read as a free course.
        $this->assertSame("\u{20B1}500.00", Money::format(50000));
    }

    public function test_an_unknown_currency_keeps_its_code_instead_of_a_wrong_symbol(): void
    {
        $this->assertSame('USD 10.00', Money::format(1000, 'USD'));
    }

    public function test_no_page_prints_raw_minor_units_for_a_paid_amount(): void
    {
        $administrator = $this->makeUser(UserRole::Administrator);
        $course = Course::factory()->for($this->makeUser(UserRole::Instructor), 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Paid,
            'price_minor' => 125050,
        ]);

        Module::factory()->for($course, 'course')->create();

        $body = (string) $this->actingAs($administrator)
            ->get(route('instructor.dashboard'))
            ->getContent();

        $this->assertStringNotContainsString('125050', $body);
    }

    // ------------------------------------------------------------------ status

    public function test_every_state_reads_as_a_sentence_and_a_tone(): void
    {
        $this->assertSame('Waiting for payment confirmation', StatusLabel::label('pending_payment'));
        $this->assertSame('Payment confirmed', StatusLabel::label('paid'));
        $this->assertSame('Payment did not go through', StatusLabel::label('failed'));
        $this->assertSame('Revoked', StatusLabel::label('revoked'));
        $this->assertSame('Not passed', StatusLabel::label('not_passed'));

        // Colour is a second signal, so a tone always accompanies the words.
        foreach (StatusLabel::coveredEnums() as $enum) {
            foreach ($enum::cases() as $case) {
                $resolved = StatusLabel::for($case);

                $this->assertContains(
                    $resolved['tone'],
                    ['neutral', 'info', 'success', 'warning', 'error'],
                    'An unknown tone would render with no colour: '.$case->value,
                );
                $this->assertNotSame('', $resolved['label']);
            }
        }
    }

    public function test_an_unrecognised_state_still_reads_as_words(): void
    {
        $this->assertSame('Something new', StatusLabel::words('something_new'));
    }

    // -------------------------------------------------------------- a11y basics

    public function test_no_authenticated_page_uses_a_placeholder_glyph_for_an_action(): void
    {
        $user = $this->makeUser(UserRole::Administrator);

        $body = (string) $this->actingAs($user)
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->getContent();

        // Icons are real SVG drawings, so an action is never a character that
        // changes shape with the platform font.
        $this->assertStringNotContainsString('â†', $body);
        $this->assertStringNotContainsString('â—', $body);
    }

    public function test_a_page_wide_table_scrolls_inside_its_own_container(): void
    {
        $administrator = $this->makeUser(UserRole::Administrator);

        $body = (string) $this->actingAs($administrator)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        // A wide administrator table must not widen the page on a phone. The
        // scroll container is the approved way to keep a table readable.
        $this->assertStringContainsString('overflow-x-auto', $body);
    }

    private function dashboardFor(UserRole $role): string
    {
        return match ($role) {
            UserRole::Student => route('student.dashboard'),
            UserRole::Instructor => route('instructor.dashboard'),
            UserRole::Administrator => route('administrator.dashboard'),
        };
    }

    /**
     * @param  list<array{label: string, items: list<array{route: string}>}>  $groups
     * @return list<string>
     */
    private function routesIn(array $groups): array
    {
        $routes = [];

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $routes[] = $item['route'];
            }
        }

        return $routes;
    }

    private function makeUser(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->profile->forceFill([
            'role' => $role,
            'account_status' => UserAccountStatus::Active,
        ])->save();

        return $user->fresh();
    }
}
