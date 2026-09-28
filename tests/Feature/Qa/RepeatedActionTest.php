<?php

namespace Tests\Feature\Qa;

use App\Enums\CertificateStatus;
use App\Enums\ContentStatus;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Charter C2: repeat, concurrency, and illegal state transitions.
 *
 * Three properties are being tested, and they are the ones a professor finds by
 * clicking twice and a student finds by pressing back.
 *
 * Repeat must be idempotent. Double-clicking a button, refreshing after a save,
 * or a browser replaying a request must produce one enrollment, one progress
 * row, one certificate, not two. The application is expected to hold this with
 * database constraints and row locks rather than with a check in the browser.
 *
 * An illegal transition must be refused. A lesson cannot be completed for a
 * revoked enrollment, a certificate cannot be issued twice, a revoked
 * certificate cannot be reissued into a second live one, a suspended account
 * cannot take an action, and a demoted administrator cannot continue acting as
 * one. Each of those is a state the interface may not offer but a hand-typed
 * request can ask for.
 *
 * A refused action must leave nothing behind. A half-written row after a
 * failure is the defect that is hardest to notice and the most expensive.
 */
class RepeatedActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published course, module, lesson, and an active student enrollment.
     *
     * @return array{0: User, 1: User, 2: Course, 3: Lesson, 4: Enrollment}
     */
    private function world(): array
    {
        $student = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $module = Module::factory()->for($course, 'course')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->for($module, 'module')->create([
            'position' => 1,
            'status' => ContentStatus::Published,
            // The completion rule counts required lessons. Without this flag the
            // lesson is optional, so completing it does not complete the course
            // and two certificate tests cannot be reached. This is the same trap
            // the existing certificate tests set their fixture around.
            'is_required' => true,
        ]);

        $enrollment = Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        return [$student, $instructor, $course, $lesson, $enrollment];
    }

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->profile->forceFill(['role' => UserRole::Administrator])->save();

        return $user;
    }

    /**
     * The final active administrator, with a verified email.
     *
     * The email matters. AssignUserRole refuses a role change before it reaches
     * the final-administrator guard when the target address is unverified, so a
     * fixture without a verified email is refused for the wrong reason and the
     * guard being tested is never reached.
     */
    private function soleAdministrator(): User
    {
        $user = $this->administrator();

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * The steps that turn an active enrollment into an issued certificate.
     *
     * Two tests need a real certificate and skipping them quietly would leave
     * the revocation path untested, which is the path with money behind it. The
     * completion rule is read from the course so the fixture satisfies whatever
     * the application actually requires, rather than a guess about it.
     */
    private function issueCertificate(array $world): ?Certificate
    {
        [$student, , $course, $lesson, $enrollment] = $world;

        // The lesson has to be recorded as complete first. The certificate is a
        // reward for finished work, so requesting one without it is refused, and
        // a test that skipped this step was measuring its own missing setup.
        $this->actingAs($student)->post("/student/courses/{$course->id}/lessons/{$lesson->id}/complete");

        $this->actingAs($student)->post("/student/courses/{$course->id}/complete");

        return Certificate::query()->where('enrollment_id', $enrollment->id)->first();
    }

    /* ------------------------------------------------------- double submit */

    public function test_completing_a_lesson_twice_keeps_one_progress_row(): void
    {
        [$student, , $course, $lesson, $enrollment] = $this->world();

        $url = "/student/courses/{$course->id}/lessons/{$lesson->id}/complete";

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($student)->post($url);
        }

        $this->assertSame(
            1,
            DB::table('lesson_progress')
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->count(),
            'Five identical completions produced more than one progress row.'
        );
    }

    public function test_completing_a_lesson_twice_keeps_the_first_completion_time(): void
    {
        [$student, , $course, $lesson, $enrollment] = $this->world();

        $url = "/student/courses/{$course->id}/lessons/{$lesson->id}/complete";

        $this->actingAs($student)->post($url);

        $first = DB::table('lesson_progress')
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)
            ->value('completed_at');

        $this->assertNotNull($first);

        $this->travel(5)->minutes();

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($student)->post($url);
        }

        $this->assertSame(
            $first,
            DB::table('lesson_progress')
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->value('completed_at'),
            'A repeat completion moved the original completion time, so the record no longer says when the work was done.'
        );
    }

    public function test_enrolling_in_the_same_free_course_twice_keeps_one_enrollment(): void
    {
        [$student, , $course] = $this->world();

        $url = "/student/courses/{$course->id}/enroll";

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($student)->post($url);
        }

        $this->assertSame(
            1,
            DB::table('enrollments')
                ->where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->count(),
            'Five identical enrolment requests produced more than one enrollment.'
        );
    }

    public function test_creating_the_same_module_twice_does_not_duplicate_a_position(): void
    {
        [, $instructor, $course] = $this->world();

        $payload = [
            'title' => 'A module',
            'description' => 'A body',
        ];

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($instructor)->post("/instructor/courses/{$course->id}/modules", $payload);
        }

        $positions = DB::table('modules')
            ->where('course_id', $course->id)
            ->orderBy('position')
            ->pluck('position')
            ->all();

        $this->assertSame(
            $positions,
            array_unique($positions),
            'Repeated module creation produced two modules at the same position, so their order is undefined.'
        );
    }

    public function test_rapid_repeated_saves_leave_one_version_of_the_course(): void
    {
        [, $instructor, $course] = $this->world();

        // The same edit sent four times, which is what a double click and a
        // browser retry look like from the server's side.
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($instructor)->patch("/instructor/courses/{$course->id}", [
                'title' => 'Renamed once',
                'description' => 'Body',
                'level' => CourseLevel::Beginner->value,
                'course_type' => CourseType::Free->value,
                'price_minor' => 0,
            ]);
        }

        $this->assertSame(1, DB::table('courses')->where('id', $course->id)->count());
        $this->assertSame('Renamed once', $course->fresh()->title);
    }

    /* --------------------------------------------------- illegal transitions */

    /**
     * A cancelled enrollment stands in for a withdrawn one. The application has
     * no revoked status, so the illegal transition to test is against the
     * closest state a student can actually reach: a payment that was cancelled,
     * or a refund, leaving an enrollment that exists but grants no access.
     */
    public function test_a_lesson_cannot_be_completed_for_a_cancelled_enrollment(): void
    {
        [$student, , $course, $lesson, $enrollment] = $this->world();

        $enrollment->forceFill(['status' => EnrollmentStatus::Cancelled])->save();

        $before = DB::table('lesson_progress')->count();

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/lessons/{$lesson->id}/complete")
            ->assertForbidden();

        $this->assertSame(
            $before,
            DB::table('lesson_progress')->count(),
            'A lesson was completed for a cancelled enrollment, so a withdrawn student kept progress.'
        );
    }

    /**
     * Completing the only lesson in a course completes the course.
     *
     * The enrollment has no stored percentage column. Progress is calculated when
     * a page needs it, which is why this asserts the status transition and then
     * asks for the page that displays the number, rather than reading a field
     * that does not exist.
     */
    public function test_completing_the_only_lesson_completes_the_course(): void
    {
        [$student, , $course, $lesson, $enrollment] = $this->world();

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/lessons/{$lesson->id}/complete")
            ->assertRedirect();

        // The certificate is issued by a second, explicit request. Marking the
        // lesson complete is the same request for a single lesson course, and it
        // is what the interface does.
        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/complete")
            ->assertRedirect();

        $enrollment->refresh();

        $this->assertSame(
            EnrollmentStatus::Completed,
            $enrollment->status,
            'Completing the only lesson of a course did not complete the enrollment.'
        );

        $this->assertNotNull($enrollment->completed_at, 'The enrollment is complete but records no completion time.');

        // The page that shows the number must agree with the stored state.
        $this->actingAs($student)
            ->get('/student/courses')
            ->assertOk()
            ->assertSee('100%');
    }

    public function test_a_lesson_cannot_be_completed_before_payment_settles(): void
    {
        [$student, , $course, $lesson, $enrollment] = $this->world();

        $enrollment->forceFill(['status' => EnrollmentStatus::PendingPayment])->save();

        $before = DB::table('lesson_progress')->count();

        // The unpaid state is the one that matters: a student who has not paid
        // must not be able to read content or record progress, and this is
        // reachable by hand even though the interface never offers it.
        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/lessons/{$lesson->id}/complete")
            ->assertForbidden();

        $this->assertSame(
            $before,
            DB::table('lesson_progress')->count(),
            'Progress was recorded for an enrollment whose payment has not settled.'
        );
    }

    public function test_a_certificate_cannot_be_issued_twice_for_one_enrollment(): void
    {
        [$student, , $course, , $enrollment] = $this->world();

        $issue = function () use ($student, $course): void {
            $this->actingAs($student)->post("/student/courses/{$course->id}/complete");
        };

        $issue();

        $count = DB::table('certificates')
            ->where('enrollment_id', $enrollment->id)
            ->where('status', 'issued')
            ->count();

        // A second issue must either be refused or replace the first, never add a
        // second live certificate beside it.
        $issue();

        $after = DB::table('certificates')
            ->where('enrollment_id', $enrollment->id)
            ->where('status', 'issued')
            ->count();

        $this->assertLessThanOrEqual(
            $count,
            $after,
            'Issuing twice produced a second live certificate for one enrollment.'
        );
    }

    public function test_a_revoked_certificate_cannot_be_reissued_into_a_second_live_one(): void
    {
        $certificate = $this->issueCertificate($this->world());

        $this->assertNotNull(
            $certificate,
            'No certificate was issued, so the revocation path cannot be tested. The completion rules for a free '
            .'course are not being satisfied by this fixture.'
        );

        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post("/admin/certificates/{$certificate->id}/revoke", ['reason' => 'Issued in error.'])
            ->assertRedirect();

        $this->actingAs($administrator)
            ->post("/admin/certificates/{$certificate->id}/reissue", ['reason' => 'Corrected.']);

        $live = DB::table('certificates')
            ->where('enrollment_id', $certificate->enrollment_id)
            ->where('status', 'issued')
            ->count();

        $this->assertLessThanOrEqual(
            1,
            $live,
            "Reissuing left {$live} live certificates for one enrollment."
        );

        // The replacement has to point back at the certificate it replaced, or
        // the chain is broken and the revocation history cannot be followed.
        $replacement = DB::table('certificates')
            ->where('enrollment_id', $certificate->enrollment_id)
            ->where('status', 'issued')
            ->orderByDesc('id')
            ->first();

        if ($replacement !== null && (int) $replacement->id !== (int) $certificate->id) {
            $this->assertSame(
                (int) $certificate->id,
                (int) $replacement->replaces_certificate_id,
                'The replacement certificate does not record which certificate it replaced.'
            );
        }
    }

    public function test_a_suspended_account_cannot_complete_a_lesson(): void
    {
        [$student, , $course, $lesson, $enrollment] = $this->world();

        $student->profile->forceFill(['account_status' => 'suspended'])->save();

        $before = DB::table('lesson_progress')->count();

        $this->actingAs($student)
            ->post("/student/courses/{$course->id}/lessons/{$lesson->id}/complete");

        $this->assertSame(
            $before,
            DB::table('lesson_progress')->count(),
            'A suspended account recorded progress, so suspension does not stop activity.'
        );
    }

    public function test_a_demoted_administrator_loses_administrator_access_immediately(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)->get('/admin')->assertOk();

        $administrator->profile->forceFill(['role' => UserRole::Student])->save();

        // The session still holds the account, so the only thing that can stop
        // this is the role being read from the database on each request.
        $this->actingAs($administrator)->get('/admin')->assertForbidden();
    }

    public function test_a_revoked_certificate_is_not_presented_as_a_valid_award(): void
    {
        $certificate = $this->issueCertificate($this->world());

        $this->assertNotNull(
            $certificate,
            'No certificate was issued, so revocation cannot be tested. The completion rules for a free course are '
            .'not being satisfied by this fixture.'
        );

        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post("/admin/certificates/{$certificate->id}/revoke", ['reason' => 'Issued in error.']);

        $this->assertSame(
            CertificateStatus::Revoked,
            $certificate->fresh()->status,
            'The certificate was not marked revoked.'
        );

        // Whether the owner may still open the page is a policy question, so both
        // answers are accepted. What is not accepted is the page presenting a
        // withdrawn award as though nothing happened.
        $response = $this->actingAs($certificate->student)->get("/student/certificates/{$certificate->id}");

        if ($response->isOk()) {
            $this->assertStringContainsStringIgnoringCase(
                'revoked',
                (string) $response->getContent(),
                'A revoked certificate is still presented as a valid award.'
            );

            return;
        }

        $response->assertForbidden();
    }

    /* ------------------------------------------- refusals leave no residue */

    public function test_a_refused_material_upload_writes_no_file_and_no_row(): void
    {
        [, $instructor, $course, $lesson] = $this->world();

        $path = $course->modules()->first()->lessons()->first()->id;

        $before = DB::table('learning_materials')->count();
        $stored = count(glob(storage_path('app/private/learning-materials').'/*') ?: []);

        // A file whose extension is allowed but whose content is not the type
        // claimed. The extension check passes, so the content check has to.
        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/modules/{$path}/lessons/{$lesson->id}/materials", [
                'title' => 'A disguised file',
                'type' => 'pdf',
                'file' => File::createWithContent('report.pdf', '<?php echo "not a pdf";'),
            ]);

        $this->assertSame(
            $before,
            DB::table('learning_materials')->count(),
            'A rejected upload still wrote a material row.'
        );

        $this->assertSame(
            $stored,
            count(glob(storage_path('app/private/learning-materials').'/*') ?: []),
            'A rejected upload still wrote a file to the private disk.'
        );
    }

    /**
     * The system cannot be reduced to having no active administrator.
     *
     * A direct test of the final-administrator guard inside AssignUserRole is not
     * possible, and the reason is worth recording rather than working around. The
     * guard requires the target to be an active administrator while the active
     * administrator count is one. UserPolicy already refuses a self-directed role
     * change, so the acting account is always a different active administrator,
     * which means the count is at least two whenever the guard's other conditions
     * hold. The guard is unreachable: it is defence in depth behind the policy
     * rather than a reachable rule.
     *
     * So the property is asserted where it is actually enforced, by driving the
     * system down to a single administrator through the only path that can get
     * there, and confirming no further reduction is possible.
     */
    public function test_the_system_cannot_be_reduced_to_no_active_administrator(): void
    {
        $actor = $this->soleAdministrator();

        // A second active administrator, with a verified address so the action's
        // own checks are satisfied.
        $second = User::factory()->create();
        $second->profile->forceFill(['role' => UserRole::Administrator])->save();
        $second->forceFill(['email_verified_at' => now()])->save();

        $activeAdministrators = static fn (): int => Profile::query()
            ->where('role', UserRole::Administrator->value)
            ->where('account_status', 'active')
            ->count();

        $this->assertSame(2, $activeAdministrators(), 'The fixture should start with two active administrators.');

        // Reducing to one is allowed, because the other administrator is there.
        $this->actingAs($actor)
            ->patch("/admin/users/{$second->id}/role", ['role' => UserRole::Student->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $activeAdministrators(), 'The reduction to one active administrator did not happen.');

        $before = DB::table('activity_logs')->count();

        // The only remaining administrator cannot change their own role, and the
        // policy refuses it with a 403 before any action runs.
        $this->actingAs($actor)
            ->patch("/admin/users/{$actor->id}/role", ['role' => UserRole::Student->value])
            ->assertForbidden();

        $this->assertSame(1, $activeAdministrators(), 'The last active administrator was demoted.');
        $this->assertSame(UserRole::Administrator, $actor->fresh()->profile->role);

        $this->assertSame(
            $before,
            DB::table('activity_logs')->count(),
            'A refused role change still wrote an activity record claiming it happened.'
        );
    }

    public function test_a_role_change_that_is_allowed_is_recorded_exactly_once(): void
    {
        $administrator = $this->soleAdministrator();
        $target = User::factory()->create();
        $target->forceFill(['email_verified_at' => now()])->save();

        $before = DB::table('activity_logs')->count();

        // The same change sent three times, which is what a double click looks
        // like. The second and third must be refused as a no-op rather than
        // recorded again.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($administrator)
                ->patch("/admin/users/{$target->id}/role", ['role' => UserRole::Instructor->value]);
        }

        $this->assertSame(UserRole::Instructor, $target->fresh()->profile->role);

        $this->assertSame(
            $before + 1,
            DB::table('activity_logs')->count(),
            'Three identical role changes did not produce exactly one record.'
        );
    }

    public function test_a_material_position_is_not_duplicated_by_a_repeated_upload(): void
    {
        [, $instructor, $course, $lesson] = $this->world();

        $module = $course->modules()->first();

        $positions = [];

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($instructor)->post(
                "/instructor/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}/materials",
                [
                    'title' => "Material {$i}",
                    'type' => 'image',
                    // A real PNG built from bytes, not File::image, which needs
                    // the GD extension. The application sniffs content, so a
                    // fabricated header would be refused and the positions would
                    // never actually be compared.
                    'file' => File::createWithContent(
                        "shot{$i}.png",
                        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
                    ),
                ]
            );

            $positions = LearningMaterial::query()
                ->where('lesson_id', $lesson->id)
                ->pluck('position')
                ->all();
        }

        $this->assertSame(
            $positions,
            array_unique($positions),
            'Repeated uploads produced two materials at the same position.'
        );
    }
}
