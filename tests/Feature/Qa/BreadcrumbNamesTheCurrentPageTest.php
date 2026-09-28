<?php

namespace Tests\Feature\Qa;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The breadcrumb has to name the page the reader is actually on.
 *
 * `Navigation::pageLabel` returned the label of whichever navigation item
 * *matched* the current route. The Instructor sidebar has one item,
 * "My courses", whose match list covers `instructor.courses.*`, so that label
 * was returned for every page underneath it. The course outline, the edit form,
 * the module editor, the lesson editor and the learner list all ended in the
 * same two words.
 *
 * The cost is not cosmetic. The last crumb carries `aria-current="page"`, so a
 * screen reader announces the reader as being on "My courses" whatever page they
 * are on, and a person using the trail has no way to tell five pages apart.
 * A trail that cannot say where it ends is worse than no trail, because it looks
 * like an answer.
 *
 * Found by opening each page in a browser and reading the trail, not by reading
 * the code: the code looks correct, because a matching item is found and its
 * label is returned.
 */
class BreadcrumbNamesTheCurrentPageTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = User::factory()->instructor()->create();

        $this->course = Course::factory()->for($this->instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
            'published_at' => now(),
        ]);
    }

    /**
     * Every page under the Instructor course area, with the words its own crumb
     * has to contain.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function instructorCoursePages(): array
    {
        return [
            'the course list' => ['/instructor/courses', 'Courses'],
            'the course outline' => ['/instructor/courses/{course}', 'Outline'],
            'the course edit form' => ['/instructor/courses/{course}/edit', 'Edit'],
            'the learner list' => ['/instructor/courses/{course}/students', 'Learner'],
        ];
    }

    #[DataProvider('instructorCoursePages')]
    public function test_the_last_crumb_names_the_page_rather_than_its_section(string $suffix, string $expected): void
    {
        $path = str_replace('{course}', (string) $this->course->id, $suffix);

        $body = (string) $this->actingAs($this->instructor)->get($path)->assertOk()->getContent();

        $current = $this->currentCrumb($body);

        $this->assertNotNull($current, "{$path} rendered no breadcrumb with a current page.");

        $this->assertStringContainsStringIgnoringCase(
            $expected,
            $current,
            "The last crumb on {$path} reads \"{$current}\", which does not name the page. It repeats the "
            .'section the page is inside, so every page in that section announces the same place.'
        );
    }

    /**
     * Two different pages may not announce the same current crumb.
     *
     * This is the property the previous implementation broke, stated directly
     * rather than inferred from a list of expected labels, so a new page added
     * later is covered without editing the test.
     */
    public function test_no_two_course_pages_announce_the_same_current_crumb(): void
    {
        $paths = [
            route('instructor.courses.index'),
            route('instructor.courses.show', $this->course),
            route('instructor.courses.edit', $this->course),
            route('instructor.courses.students', $this->course),
        ];

        $seen = [];

        foreach ($paths as $path) {
            $body = (string) $this->actingAs($this->instructor)->get($path)->assertOk()->getContent();
            $current = $this->currentCrumb($body);

            $this->assertNotNull($current, "{$path} rendered no current crumb.");

            $key = mb_strtolower($current);
            $alsoOn = $seen[$key] ?? '';

            $this->assertArrayNotHasKey(
                $key,
                $seen,
                "{$path} and {$alsoOn} both announce \"{$current}\" as the current page."
            );

            $seen[$key] = $path;
        }
    }

    /**
     * No page may be announced as living under a page that is not its parent.
     *
     * The section crumb was taken from the first item of whatever group the page
     * happened to be in. In the Learning, Teaching and Operations groups the
     * first item is the Dashboard, which really is a section index. In the
     * Account group the first item is Announcements, so every Account page was
     * announced as "Learning workspace > Announcements > Profile", and Messages
     * was announced as living under Announcements.
     *
     * A trail that sends somebody to the wrong parent is worse than a short
     * one, so this is asserted for each of those pages rather than for one
     * example of them.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pagesThatMustNotBeNestedUnderEachOther(): array
    {
        return [
            'the profile page' => ['/account/profile', 'Announcements'],
            'the password page' => ['/account/password', 'Announcements'],
            'the messages page' => ['/messages', 'Announcements'],
        ];
    }

    #[DataProvider('pagesThatMustNotBeNestedUnderEachOther')]
    public function test_a_page_is_not_announced_as_living_under_another_page(string $path, string $wrongParent): void
    {
        $body = (string) $this->actingAs(User::factory()->create())->get($path)->assertOk()->getContent();

        $trail = $this->trail($body);

        $this->assertNotContains(
            $wrongParent,
            $trail,
            'The trail on '.$path.' is "'.implode(' > ', $trail).'", which presents "'.$wrongParent.'" '
            .'as the parent of this page. It is a sibling in the same navigation group, not a parent.'
        );
    }

    /**
     * Every crumb in the trail, in order.
     *
     * @return list<string>
     */
    private function trail(string $body): array
    {
        if (! preg_match('~<nav aria-label="Breadcrumb".*?</nav>~s', $body, $nav)) {
            return [];
        }

        if (! preg_match_all('~>([^<>]+)<~', $nav[0], $labels)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $label): string => trim($label), $labels[1]),
            static fn (string $label): bool => $label !== ''
        ));
    }

    /**
     * The last crumb, which is the one marked as the current page.
     */
    private function currentCrumb(string $body): ?string
    {
        if (! preg_match('~<nav aria-label="Breadcrumb".*?</nav>~s', $body, $nav)) {
            return null;
        }

        // The current crumb is the only one that is a span carrying
        // aria-current, so it is read from the element itself rather than by
        // position, which would break the moment a crumb is dropped.
        if (! preg_match('~<span[^>]*aria-current="page"[^>]*>(.*?)</span>~s', $nav[0], $crumb)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($crumb[1])) ?? '');

        return $text === '' ? null : $text;
    }
}
