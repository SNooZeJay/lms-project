<?php

namespace Tests\Feature\Announcements;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * That an announcement can actually be written, from the pages it is written on.
 *
 * Announcements were approved with a controller, a validated request, a policy
 * answering both publishing questions, and routes for both scopes. Reading worked
 * from the first day and so did withdrawing one. Publishing could not be reached
 * from anywhere: no form on any page posted to either route, which a crawl of
 * every reachable page confirmed across 66 pages and 339 form actions.
 *
 * A feature that cannot be used is a controller with a test, and the tests that
 * existed all posted to the routes directly, so nothing noticed. These tests go
 * through the pages, because that is the way a person reaches it and the way the
 * fault was invisible.
 */
class AnnouncementPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->profile->forceFill(['role' => UserRole::Administrator])->save();

        return $user->fresh();
    }

    /**
     * A published course, its instructor, and an enrolled student.
     *
     * @return array{0: Course, 1: User, 2: User}
     */
    private function courseWithStudent(): array
    {
        $instructor = $this->instructor();
        $student = User::factory()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return [$course, $instructor, $student];
    }

    /* ------------------------------------------------- the forms are on the page */

    public function test_the_instructor_course_page_offers_the_announcement_form(): void
    {
        [$course, $instructor] = $this->courseWithStudent();

        $this->actingAs($instructor)
            ->get(route('instructor.courses.show', $course))
            ->assertOk()
            ->assertSee('Announce something to this course')
            ->assertSee(route('instructor.courses.announcements.store', $course), escape: false);
    }

    public function test_the_administrator_announcement_page_offers_the_platform_form(): void
    {
        $this->actingAs($this->admin())
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Tell everybody something')
            ->assertSee(route('admin.announcements.store'), escape: false);
    }

    /**
     * The gate is the part that silently fails. `@can('createPlatform')` with no
     * model binds nothing, so there is no policy to look in and the answer is
     * always false. Both abilities live on AnnouncementPolicy, so the form has to
     * name the model, and this is the assertion that says it still does.
     */
    public function test_the_forms_are_absent_for_roles_that_may_not_publish(): void
    {
        [$course, , $student] = $this->courseWithStudent();
        $instructor = $this->instructor();

        $this->actingAs($student)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee(route('admin.announcements.store'), escape: false);

        $this->actingAs($instructor)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee(route('admin.announcements.store'), escape: false);

        // A student cannot reach the instructor page at all, so the absence of
        // the course form there is enforced by the page, not by the gate.
        $this->actingAs($student)
            ->get(route('instructor.courses.show', $course))
            ->assertForbidden();
    }

    /* --------------------------------------------------- publishing through them */

    public function test_an_instructor_publishes_to_a_course_from_the_course_page(): void
    {
        [$course, $instructor, $student] = $this->courseWithStudent();

        $this->actingAs($instructor)
            ->post(route('instructor.courses.announcements.store', $course), [
                'title' => 'Bring a laptop on Thursday',
                'body' => 'The second workshop needs a machine you can install things on.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'course_id' => $course->id,
            'title' => 'Bring a laptop on Thursday',
        ]);

        $this->actingAs($student)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Bring a laptop on Thursday');
    }

    public function test_an_administrator_publishes_to_everybody_from_the_announcement_page(): void
    {
        $admin = $this->admin();
        $student = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'Catalog check on Sunday',
                'body' => 'The catalog is being checked while nobody is using it.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'course_id' => null,
            'title' => 'Catalog check on Sunday',
        ]);

        $this->actingAs($student)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Catalog check on Sunday');
    }

    /* ---------------------------------------------------------- the rules hold */

    public function test_a_course_announcement_does_not_reach_somebody_not_enrolled(): void
    {
        [$course, $instructor] = $this->courseWithStudent();
        $stranger = User::factory()->create();

        $this->actingAs($instructor)
            ->post(route('instructor.courses.announcements.store', $course), [
                'title' => 'Only for this course',
                'body' => 'This should not be visible to somebody who is not enrolled.',
            ]);

        $this->actingAs($stranger)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('Only for this course');
    }

    public function test_an_instructor_may_not_announce_to_everybody(): void
    {
        [, $instructor] = $this->courseWithStudent();

        $this->actingAs($instructor)
            ->post(route('admin.announcements.store'), [
                'title' => 'Trying to speak to everybody',
                'body' => 'An instructor announcing to the whole platform.',
            ])
            ->assertForbidden();
    }

    public function test_an_empty_announcement_is_refused_with_an_explanation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('announcements.index'))
            ->post(route('admin.announcements.store'), ['title' => '', 'body' => ''])
            ->assertRedirect(route('announcements.index'))
            ->assertSessionHasErrors(['title', 'body']);

        $this->assertDatabaseCount('announcements', 0);
    }
}
