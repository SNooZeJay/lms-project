<?php

namespace Tests\Feature\Phase7A;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_reorder_modules(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), ['positions' => [
                $modules[0]->id => 3,
                $modules[1]->id => 1,
                $modules[2]->id => 2,
            ]])
            ->assertRedirect($this->courseShowUrl($course))
            ->assertSessionHas('status');

        $ordered = $course->modules()->orderBy('position')->pluck('id')->all();

        $this->assertSame([$modules[1]->id, $modules[2]->id, $modules[0]->id], $ordered);
    }

    public function test_reorder_leaves_no_gaps_or_duplicates(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();

        $this->actingAs($instructor)->from($this->courseShowUrl($course))->patch(
            $this->moduleReorderUrl($course),
            ['positions' => [$modules[0]->id => 2, $modules[1]->id => 3, $modules[2]->id => 1]]
        );

        $positions = $course->modules()->orderBy('position')->pluck('position')->all();

        $this->assertSame([1, 2, 3], $positions);
        $this->assertSame($positions, array_unique($positions));
    }

    public function test_reorder_keeps_titles_and_statuses(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();

        $this->actingAs($instructor)->from($this->courseShowUrl($course))->patch(
            $this->moduleReorderUrl($course),
            ['positions' => [$modules[0]->id => 2, $modules[1]->id => 3, $modules[2]->id => 1]]
        );

        $first = $course->modules()->orderBy('position')->first();

        $this->assertSame($modules[2]->title, $first->title);
        $this->assertSame(ContentStatus::Published, $first->status);
        $this->assertNotSame($modules[2]->position, $first->position);
    }

    public function test_reorder_rejects_a_missing_module(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), ['positions' => [
                $modules[0]->id => 1,
                $modules[1]->id => 2,
            ]])
            ->assertSessionHasErrors('positions');

        $this->assertSame(
            [$modules[0]->id, $modules[1]->id, $modules[2]->id],
            $course->modules()->orderBy('position')->pluck('id')->all()
        );
    }

    public function test_reorder_rejects_a_module_from_another_course(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();
        $otherCourse = $this->publishedCourse($instructor);
        $foreign = Module::factory()->for($otherCourse, 'course')->create(['position' => 1]);

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), [
                'positions' => [
                    $modules[0]->id => 1,
                    $modules[1]->id => 2,
                    $foreign->id => 3,
                ],
            ])
            ->assertSessionHasErrors('positions');

        $this->assertSame(1, $foreign->fresh()->position);
    }

    public function test_reorder_rejects_repeated_or_gapped_positions(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), [
                'positions' => [$modules[0]->id => 1, $modules[1]->id => 1, $modules[2]->id => 1],
            ])
            ->assertSessionHasErrors('positions');

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), [
                'positions' => [$modules[0]->id => 1, $modules[1]->id => 2, $modules[2]->id => 9],
            ])
            ->assertSessionHasErrors('positions');
    }

    public function test_reorder_rejects_an_empty_or_missing_payload(): void
    {
        [$instructor, $course] = $this->courseWithThreeModules();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), ['positions' => []])
            ->assertSessionHasErrors('positions');

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course))
            ->assertSessionHasErrors('positions');
    }

    public function test_instructor_can_reorder_lessons(): void
    {
        [$instructor, $course, $module, $lessons] = $this->courseWithThreeLessons();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->lessonReorderUrl($course, $module), ['positions' => [
                $lessons[0]->id => 2,
                $lessons[1]->id => 3,
                $lessons[2]->id => 1,
            ]])
            ->assertRedirect($this->courseShowUrl($course));

        $this->assertSame(
            [$lessons[2]->id, $lessons[0]->id, $lessons[1]->id],
            $module->lessons()->orderBy('position')->pluck('id')->all()
        );
    }

    public function test_lesson_reorder_rejects_a_lesson_from_another_module(): void
    {
        [$instructor, $course, $module, $lessons] = $this->courseWithThreeLessons();
        $otherModule = Module::factory()->for($course, 'course')->create([
            'position' => 4,
            'status' => ContentStatus::Published,
        ]);
        $foreign = Lesson::factory()->for($otherModule, 'module')->create(['position' => 1]);

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->lessonReorderUrl($course, $module), ['positions' => [
                $lessons[0]->id => 1,
                $lessons[1]->id => 2,
                $lessons[2]->id => 3,
                $foreign->id => 4,
            ]])
            ->assertSessionHasErrors('positions');

        $this->assertSame(1, $foreign->fresh()->position);
    }

    public function test_lesson_reorder_returns_not_found_for_a_module_from_another_course(): void
    {
        [$instructor, $course, , $lessons] = $this->courseWithThreeLessons();
        $otherCourse = $this->publishedCourse($instructor);
        $otherModule = Module::factory()->for($otherCourse, 'course')->create(['position' => 1]);

        $this->actingAs($instructor)
            ->patch($this->lessonReorderUrl($course, $otherModule), ['positions' => [
                $lessons[0]->id => 1,
            ]])
            ->assertNotFound();
    }

    public function test_another_instructor_cannot_reorder(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();
        $stranger = User::factory()->instructor()->create();

        $this->actingAs($stranger)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), [
                'positions' => [$modules[0]->id => 3, $modules[1]->id => 1, $modules[2]->id => 2],
            ])
            ->assertForbidden();

        $this->assertSame($modules[0]->id, $course->modules()->orderBy('position')->first()->id);
    }

    public function test_student_and_administrator_cannot_reorder(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();
        $module = $modules[0];
        $student = User::factory()->create();
        $administrator = User::factory()->create();
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $this->actingAs($student)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), ['positions' => []])
            ->assertForbidden();

        $this->actingAs($administrator)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), ['positions' => []])
            ->assertForbidden();

        $this->actingAs($student)
            ->from($this->courseShowUrl($course))
            ->patch($this->lessonReorderUrl($course, $module), ['positions' => []])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [, $course, $modules] = $this->courseWithThreeModules();
        $module = $modules[0];

        $this->patch($this->moduleReorderUrl($course), ['positions' => []])->assertRedirect(route('login'));
        $this->patch($this->lessonReorderUrl($course, $module), ['positions' => []])
            ->assertRedirect(route('login'));
    }

    public function test_reorder_ignores_extra_status_and_owner_fields(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();

        $this->actingAs($instructor)->from($this->courseShowUrl($course))->patch(
            $this->moduleReorderUrl($course),
            [
                'positions' => [$modules[0]->id => 3, $modules[1]->id => 1, $modules[2]->id => 2],
                'status' => 'archived',
                'course_id' => 999999,
                'title' => 'Hijacked',
            ]
        )->assertRedirect();

        $this->assertSame(CourseStatus::Published, $course->fresh()->status);
        $this->assertSame(
            $modules[0]->title,
            (string) $course->modules()->where('id', $modules[0]->id)->first()->title
        );
    }

    public function test_archived_modules_are_kept_but_not_reorderable(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();
        $modules[1]->forceFill(['status' => ContentStatus::Archived])->save();

        $this->actingAs($instructor)
            ->from($this->courseShowUrl($course))
            ->patch($this->moduleReorderUrl($course), ['positions' => [
                $modules[0]->id => 1,
                $modules[1]->id => 2,
                $modules[2]->id => 3,
            ]])
            ->assertSessionHasErrors('positions');

        $this->assertSame(ContentStatus::Archived, $modules[1]->fresh()->status);
    }

    public function test_student_course_page_lists_modules_in_the_new_order(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();
        $student = User::factory()->create();
        Enrollment::factory()->active()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}")
            ->assertOk()
            ->assertSeeInOrder([$modules[0]->title, $modules[1]->title, $modules[2]->title], false);

        $this->actingAs($instructor)->from($this->courseShowUrl($course))->patch(
            $this->moduleReorderUrl($course),
            ['positions' => [$modules[0]->id => 3, $modules[1]->id => 1, $modules[2]->id => 2]]
        );

        $this->actingAs($student)
            ->get("/student/courses/{$course->id}")
            ->assertOk()
            ->assertSeeInOrder([$modules[1]->title, $modules[2]->title, $modules[0]->title], false);
    }

    public function test_instructor_outline_exposes_a_working_reorder_form(): void
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();

        $this->actingAs($instructor)
            ->get($this->courseShowUrl($course))
            ->assertOk()
            ->assertSee('Reorder modules')
            ->assertSee($this->moduleReorderUrl($course), false)
            ->assertSee('positions['.$modules[0]->id.']', false)
            ->assertSee('positions['.$modules[2]->id.']', false);
    }

    public function test_lesson_reorder_form_is_rendered_for_each_module(): void
    {
        [$instructor, $course, $module, $lessons] = $this->courseWithThreeLessons();

        $this->actingAs($instructor)
            ->get($this->courseShowUrl($course))
            ->assertOk()
            ->assertSee('Reorder lessons')
            ->assertSee($this->lessonReorderUrl($course, $module), false)
            ->assertSee('positions['.$lessons[0]->id.']', false);
    }

    /**
     * @return array{0: User, 1: Course, 2: array<int, Lesson>}
     */
    private function courseWithThreeLessons(): array
    {
        [$instructor, $course, $modules] = $this->courseWithThreeModules();
        $module = $modules[0];

        $lessons = collect([1, 2, 3])->map(fn (int $position) => Lesson::factory()
            ->for($module, 'module')
            ->create([
                'position' => $position,
                'status' => ContentStatus::Published,
                'is_required' => true,
            ]))->all();

        return [$instructor, $course, $module, $lessons];
    }

    /**
     * @return array{0: User, 1: Course, 2: array<int, Module>}
     */
    private function courseWithThreeModules(): array
    {
        $instructor = User::factory()->instructor()->create();
        $course = $this->publishedCourse($instructor);

        $modules = collect([1, 2, 3])->map(fn (int $position) => Module::factory()
            ->for($course, 'course')
            ->create([
                'position' => $position,
                'status' => ContentStatus::Published,
            ]))->all();

        return [$instructor, $course, $modules];
    }

    private function publishedCourse(User $instructor): Course
    {
        return Course::factory()->for($instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);
    }

    private function courseShowUrl(Course $course): string
    {
        return "/instructor/courses/{$course->id}";
    }

    private function moduleReorderUrl(Course $course): string
    {
        return "/instructor/courses/{$course->id}/modules/reorder";
    }

    private function lessonReorderUrl(Course $course, Module $module): string
    {
        return "/instructor/courses/{$course->id}/modules/{$module->id}/lessons/reorder";
    }
}
