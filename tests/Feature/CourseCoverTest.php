<?php

namespace Tests\Feature;

use App\Actions\Courses\SetCourseCover;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Storage\CourseCoverStorage;
use App\Support\CourseCoverCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PngBuilder;
use Tests\TestCase;

/**
 * Course covers: setting one, choosing one, changing one, and taking it away.
 *
 * The cover is the first thing a person sees of a course, so it is the first thing
 * that has to be right in every place a course appears. These tests walk the whole
 * journey an instructor takes, and then read the same course from every page that
 * shows one, because a cover that works on the course page and not in the catalog is
 * a cover that is half implemented.
 *
 * Every rule here is one the database does not enforce. There is no foreign key on a
 * cover, no check on a photograph identifier, and no constraint that says an upload
 * is an image. The write path is the mechanism, and this file is what makes that
 * mechanism something other than a promise.
 */
class CourseCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The public disk is where covers live, and a test that wrote to the real one
        // would leave photographs behind that no test owns.
        Storage::fake('public');
    }

    /* --------------------------------------------------------------- arranging */

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function courseFor(User $instructor, array $attributes = []): Course
    {
        return Course::factory()->create(array_merge([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
        ], $attributes));
    }

    /**
     * A real image of a chosen size.
     *
     * Built by the project's own PNG builder rather than by the GD extension, which
     * is not installed on every machine this runs on. A test that quietly needs an
     * extension it does not have is a test that fails for a reason nobody reading it
     * would predict, and the builder needs nothing optional.
     */
    private function aRealImage(int $width = 800, int $height = 450, bool $noise = false): UploadedFile
    {
        $made = PngBuilder::at($width, $height, $noise);

        return new UploadedFile($made['path'], 'cover.png', 'image/png', null, true);
    }

    private function aTextFileNamedAsAnImage(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cover').'.jpg';
        file_put_contents($path, str_repeat('this is not an image at all. ', 40));

        return new UploadedFile($path, 'payload.jpg', 'image/jpeg', null, true);
    }

    private function firstPhotograph(): string
    {
        return CourseCoverCatalog::identifiers()[0];
    }

    /* ------------------------------------------------------ setting a cover */

    public function test_an_instructor_uploads_a_cover_for_their_own_course(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_file' => $this->aRealImage(),
            ])
            ->assertRedirect(route('instructor.courses.show', $course));

        $course->refresh();

        $this->assertNotNull($course->thumbnail_path, 'The upload was accepted and no path was stored.');
        $this->assertSame('upload', $course->cover_source);
        $this->assertSame(CourseCoverStorage::DISK, $course->cover_disk);
        $this->assertSame('image/png', $course->cover_mime_type);
        $this->assertGreaterThan(0, (int) $course->cover_byte_size);
        $this->assertTrue(
            Storage::disk('public')->exists($course->thumbnail_path),
            'The row points at a file that is not on the disk.'
        );
    }

    public function test_the_stored_path_is_generated_and_the_uploaded_name_is_not_used(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $made = PngBuilder::at(800, 450);

        $this->actingAs($instructor)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_file' => new UploadedFile($made['path'], '../../escape my cover.png', 'image/png', null, true),
            ]);

        $stored = $course->refresh()->thumbnail_path;

        $this->assertStringStartsWith('course-covers/'.$course->id.'/', (string) $stored);
        $this->assertStringNotContainsString('escape', (string) $stored, 'The uploaded filename decided where the file landed.');
        $this->assertStringNotContainsString('..', (string) $stored);
    }

    public function test_an_instructor_chooses_a_photograph_from_the_catalog(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $identifier = $this->firstPhotograph();

        $this->actingAs($instructor)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_photo_id' => $identifier,
            ])
            ->assertRedirect();

        $course->refresh();

        $this->assertSame($identifier, $course->thumbnail_path, 'The chosen photograph is not the identifier that was chosen.');
        $this->assertSame('unsplash', $course->cover_source);
        $this->assertNull($course->cover_disk, 'A photograph served from Unsplash has no file on this disk.');
        $this->assertSame('Unsplash', $course->cover_credit_name);

        $this->assertTrue(
            Storage::disk('public')->allFiles('course-covers') === [],
            'Choosing a photograph wrote a file to the disk, which it must not: the photograph is served from Unsplash.'
        );
    }

    public function test_choosing_the_same_photograph_twice_gives_the_same_cover(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $identifier = $this->firstPhotograph();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($instructor)
                ->patch(route('instructor.courses.cover.update', $course), [
                    'cover_photo_id' => $identifier,
                ]);
        }

        $course->refresh();

        $this->assertSame($identifier, $course->thumbnail_path);
        $this->assertSame(
            $course->chosenCoverUrl(640, 360),
            $course->chosenCoverUrl(640, 360),
            'The same course produced two different addresses for the same cover, so a page could show two pictures for one course.'
        );
    }

    /* ------------------------------------------------------------ changing one */

    public function test_changing_the_cover_replaces_it_in_every_view_that_shows_one(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);

        $firstPath = $course->refresh()->thumbnail_path;

        $second = CourseCoverCatalog::identifiers()[4];

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $second,
        ]);

        $course->refresh();

        $this->assertNotSame($firstPath, $course->thumbnail_path, 'The cover did not change.');

        /*
         | The same course, read from every page that shows a cover.
         |
         | This is the check that matters most and the one a test of the action alone
         | cannot make. A cover can be stored correctly and still appear as a
         | placeholder in the catalog, because the catalog reads a different column
         | or a different disk, and nothing about the write would show that.
         */
        $student = User::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
            'activated_at' => now(),
        ]);

        /*
         | Compared against the photograph's identifier, not the whole address.
         |
         | Blade escapes an ampersand when it writes an attribute, so the markup holds
         | `w=640&amp;h=360` where the model says `w=640&h=360`. Asserting on the full
         | address therefore fails on a correct page, which is what happened: the
         | first version of this test compared the two and reported a cover that was
         | plainly there. The identifier is what identifies the cover, and it is not
         | escaped, so it is what a test across rendered pages should look for.
         */
        $expected = (string) $course->thumbnail_path;

        $pages = [
            'the catalog' => fn () => $this->get(route('courses.index')),
            'the course page' => fn () => $this->get(route('courses.show', $course)),
            'the home page' => fn () => $this->get(route('home')),
            'the instructor course list' => fn () => $this->actingAs($instructor)->get(route('instructor.courses.index')),
            'the instructor course page' => fn () => $this->actingAs($instructor)->get(route('instructor.courses.show', $course)),
            'the student course list' => fn () => $this->actingAs($student)->get(route('student.courses.index')),
            'the student course page' => fn () => $this->actingAs($student)->get(route('student.courses.show', $course)),
        ];

        foreach ($pages as $label => $visit) {
            $html = $visit()->assertOk()->getContent();

            $this->assertStringContainsString(
                (string) $expected,
                $html,
                "The cover changed on the course but $label does not show the new one."
            );
        }
    }

    public function test_replacing_an_upload_removes_the_file_it_replaced(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_file' => $this->aRealImage(),
        ]);

        $original = $course->refresh()->thumbnail_path;

        $this->assertTrue(Storage::disk('public')->exists($original));

        // Now choose a photograph, which is a different kind of cover entirely.
        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);

        $this->assertFalse(
            Storage::disk('public')->exists($original),
            'The file the upload left behind is still on the disk.'
        );
    }

    public function test_replacing_an_upload_with_another_upload_removes_the_first_file(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_file' => $this->aRealImage(),
        ]);

        $first = $course->refresh()->thumbnail_path;

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_file' => $this->aRealImage(640, 360, noise: true),
        ]);

        $course->refresh();

        $this->assertNotSame($first, $course->thumbnail_path);
        $this->assertFalse(Storage::disk('public')->exists($first), 'The first upload is still on the disk.');
        $this->assertTrue(Storage::disk('public')->exists($course->thumbnail_path));
    }

    public function test_choosing_a_photograph_never_deletes_a_file_from_the_disk(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => CourseCoverCatalog::identifiers()[2],
        ]);

        $this->assertSame(
            [],
            Storage::disk('public')->allFiles(),
            'Choosing photographs wrote to, or deleted from, the disk. Neither should ever happen for a cover served from Unsplash.'
        );
    }

    /* ---------------------------------------------------------- removing one */

    public function test_removing_a_cover_leaves_the_course_on_its_placeholder(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);

        $this->actingAs($instructor)
            ->patch(route('instructor.courses.cover.update', $course), ['remove_cover' => '1'])
            ->assertRedirect(route('instructor.courses.show', $course));

        $course->refresh();

        $this->assertNull($course->thumbnail_path);
        $this->assertNull($course->cover_source);
        $this->assertNull($course->cover_credit_name);

        $html = $this->get(route('courses.show', $course))->assertOk()->getContent();

        $this->assertStringContainsString(
            'data-cover-placeholder',
            $html,
            'The course has no cover and the page is not showing the placeholder.'
        );
        $this->assertStringNotContainsString('images.unsplash.com', $html, 'A removed cover is still being displayed.');
    }

    public function test_removing_an_uploaded_cover_deletes_the_file(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_file' => $this->aRealImage(),
        ]);

        $stored = $course->refresh()->thumbnail_path;

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'remove_cover' => '1',
        ]);

        $this->assertFalse(Storage::disk('public')->exists($stored), 'The file survived the removal of the cover that pointed at it.');
    }

    public function test_a_course_with_no_cover_shows_the_placeholder_everywhere(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        foreach ([
            'the catalog' => fn () => $this->get(route('courses.index')),
            'the course page' => fn () => $this->get(route('courses.show', $course)),
            'the home page' => fn () => $this->get(route('home')),
        ] as $label => $visit) {
            $this->assertStringContainsString(
                'data-cover-placeholder',
                $visit()->assertOk()->getContent(),
                "A course with no cover shows no placeholder on $label."
            );
        }
    }

    public function test_a_cover_whose_file_has_gone_shows_the_placeholder_rather_than_a_broken_image(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_file' => $this->aRealImage(),
        ]);

        $stored = (string) $course->refresh()->thumbnail_path;

        // The disk is emptied without the row being emptied with it, which is what
        // clearing a disk or restoring a database somewhere else does.
        Storage::disk('public')->delete($stored);

        $this->assertNull(
            $course->uploadedCoverUrl(),
            'A cover whose file has gone still reports an address, so every card that shows this course requests a file that is not there.'
        );

        $html = $this->get(route('courses.show', $course))->assertOk()->getContent();

        $this->assertStringContainsString('data-cover-placeholder', $html);
    }

    /* ---------------------------------------------------------------- refusals */

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_file' => $this->aTextFileNamedAsAnImage(),
            ])
            ->assertSessionHasErrors('cover_file');

        $this->assertNull($course->refresh()->thumbnail_path, 'A text file with an image name was stored as a cover.');
    }

    public function test_an_image_too_small_to_use_is_refused_with_its_real_dimensions(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_file' => $this->aRealImage(120, 90),
            ])
            ->assertSessionHasErrors([
                'cover_file' => 'A cover must be at least 320 by 180 pixels. That one is 120 by 90.',
            ]);

        $this->assertNull($course->refresh()->thumbnail_path);
    }

    public function test_an_oversized_cover_is_refused(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        /*
         | Noise rather than a flat colour, because a gradient this size compresses to
         | a few tens of kilobytes and the limit would never be reached. 900 by 800 of
         | it is about 2.1 MB, which is just over the 2 MB limit.
         |
         | Deliberately not 4000 by 3000, which also works and also exceeded it. That
         | one is 36 MB of pixels and the test process died building the fixture,
         | before any of the application was asked anything. A size limit can be
         | reached with two and a half thousand pixels, so it is, and the assertion
         | below checks the fixture really is over the limit rather than trusting
         | this comment.
         */
        $made = PngBuilder::at(900, 800, noise: true);

        $this->assertGreaterThan(
            CourseCoverStorage::MAX_KILOBYTES * 1024,
            $made['bytes'],
            'The fixture is under the size limit, so the refusal below would be about something else.'
        );

        $this->actingAs($instructor)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_file' => new UploadedFile($made['path'], 'huge.png', 'image/png', null, true),
            ])
            ->assertSessionHasErrors('cover_file');

        $this->assertNull($course->refresh()->thumbnail_path);
    }

    public function test_a_photograph_that_is_not_on_offer_is_refused(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_photo_id' => 'photo-something-nobody-was-offered',
            ])
            ->assertSessionHasErrors('cover_photo_id');

        $this->assertNull(
            $course->refresh()->thumbnail_path,
            'A photograph nobody was offered was stored, which nothing could render and nothing could credit.'
        );
    }

    public function test_a_request_may_not_name_the_cover_columns_itself(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        foreach (['thumbnail_path', 'cover_disk', 'cover_mime_type', 'cover_source', 'cover_credit_name', 'cover_credit_url'] as $column) {
            $this->actingAs($instructor)
                ->patch(route('instructor.courses.cover.update', $course), [
                    $column => '/somewhere/else.png',
                ])
                ->assertSessionHasErrors($column);
        }

        $this->assertNull($course->refresh()->thumbnail_path);
    }

    public function test_two_ways_of_setting_a_cover_at_once_are_refused(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_file' => $this->aRealImage(),
                'cover_photo_id' => $this->firstPhotograph(),
            ])
            ->assertSessionHasErrors('cover_file');

        $this->assertNull($course->refresh()->thumbnail_path, 'One of the two answers was applied, and which one was left to the order of two fields.');
    }

    public function test_another_instructor_cannot_set_a_cover_on_a_course_they_do_not_own(): void
    {
        $owner = $this->instructor();
        $stranger = $this->instructor();
        $course = $this->courseFor($owner);

        $this->actingAs($stranger)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_photo_id' => $this->firstPhotograph(),
            ])
            ->assertForbidden();

        $this->assertNull($course->refresh()->thumbnail_path);
    }

    public function test_a_student_cannot_set_a_cover_on_any_course(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $student = User::factory()->create();

        $this->actingAs($student)
            ->patch(route('instructor.courses.cover.update', $course), [
                'cover_photo_id' => $this->firstPhotograph(),
            ])
            ->assertForbidden();

        $this->assertNull($course->refresh()->thumbnail_path);
    }

    public function test_a_signed_out_visitor_cannot_set_a_cover(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ])->assertRedirect(route('login'));

        $this->assertNull($course->refresh()->thumbnail_path);
    }

    /* ------------------------------------------------------------ what is shown */

    public function test_the_alt_text_describes_the_picture_rather_than_repeating_the_title(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor, ['title' => 'Introduction to Information Technology']);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);

        $alt = $course->refresh()->coverAltText();
        $photograph = CourseCoverCatalog::find($this->firstPhotograph());

        $this->assertSame($photograph['shows'], $alt, 'A chosen photograph should be described by what it shows.');
        $this->assertStringNotContainsString(
            $course->title,
            $alt,
            'The alt text repeats the title that is already the heading beside it, so a screen reader says the course name twice.'
        );
    }

    public function test_an_uploaded_cover_names_the_course_because_there_is_no_photograph_to_describe(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor, ['title' => 'Networking Basics']);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_file' => $this->aRealImage(),
        ]);

        $this->assertSame(
            'Cover image for Networking Basics',
            $course->refresh()->coverAltText()
        );
    }

    public function test_a_chosen_photograph_is_credited_and_an_upload_is_not(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);

        $html = $this->get(route('courses.show', $course))->assertOk()->getContent();

        $this->assertStringContainsString('Unsplash', $html, 'A photograph from Unsplash is not credited on the page it appears on.');
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $html);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_file' => $this->aRealImage(),
        ]);

        $uploaded = $this->get(route('courses.show', $course->refresh()))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'Photo:',
            $uploaded,
            'An instructor\'s own upload is credited to Unsplash, which would be attributing their file to a stranger.'
        );
    }

    public function test_a_cover_image_is_lazy_unless_it_is_the_page(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);

        $catalog = $this->get(route('courses.index'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'loading="lazy"',
            $catalog,
            'A cover in a list of courses is not deferred, so the page waits for twelve photographs.'
        );

        $page = $this->get(route('courses.show', $course))->assertOk()->getContent();

        $this->assertStringContainsString(
            'loading="eager"',
            $page,
            'The one cover on a page somebody chose to be on is deferred.'
        );

        // The first card of a home page section is above the fold, and the rest are
        // not, so the grid asks for one photograph immediately rather than six.
        $home = $this->get(route('home'))->assertOk()->getContent();
        preg_match_all('/<img[^>]*images\.unsplash\.com[^>]*>/', $home, $images);

        if ($images[0] !== []) {
            $eager = count(array_filter($images[0], static fn (string $tag): bool => str_contains($tag, 'loading="eager"')));

            $this->assertLessThanOrEqual(
                1,
                $eager,
                'More than one cover on the home page is asked for immediately, so the page waits for a whole section of photographs before it draws.'
            );
        }
    }

    public function test_a_cover_carries_width_and_height_so_the_page_does_not_shift(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);

        $html = $this->get(route('courses.show', $course))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<img[^>]+width="1200"[^>]+height="400"/',
            $html,
            'The cover has no intrinsic size in the markup, so the page moves when the image arrives.'
        );
    }

    public function test_no_page_renders_an_image_without_alt_text(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $course), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);

        foreach ([
            route('courses.index'),
            route('courses.show', $course),
            route('home'),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // Every image the cover component renders. The catalog thumbnails and the
            // brand mark are outside this, so the search is for the cover address.
            preg_match_all('/<img[^>]*images\.unsplash\.com[^>]*>/', $html, $matches);

            $this->assertNotEmpty($matches[0], "No cover was rendered on $url, so the alt text rule is untested there.");

            foreach ($matches[0] as $tag) {
                $this->assertMatchesRegularExpression(
                    '/alt="[^"]+"/',
                    $tag,
                    "A cover image on $url has no alt attribute: ".substr($tag, 0, 120)
                );
            }
        }
    }

    public function test_the_cover_of_a_draft_course_is_not_shown_to_anybody_else(): void
    {
        $instructor = $this->instructor();
        $published = $this->courseFor($instructor, ['title' => 'Published Course']);
        $draft = $this->courseFor($instructor, ['title' => 'Draft Course', 'status' => CourseStatus::Draft]);

        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $published), [
            'cover_photo_id' => $this->firstPhotograph(),
        ]);
        $this->actingAs($instructor)->patch(route('instructor.courses.cover.update', $draft), [
            'cover_photo_id' => CourseCoverCatalog::identifiers()[3],
        ]);

        // Read back, because the model in memory is the one the patch ran on and it
        // still carries the null it was created with. The first version of this
        // asserted against that null, so "the published course is in the catalog"
        // became "the string 'catalog' appears in the page", which it does.
        $this->assertNotNull($published->refresh()->thumbnail_path);
        $this->assertNotNull($draft->refresh()->thumbnail_path);

        $catalog = $this->get(route('courses.index'))->assertOk()->getContent();

        $this->assertStringContainsString(
            (string) $published->thumbnail_path,
            $catalog,
            'The published course and its cover are not in the catalog.'
        );
        $this->assertStringNotContainsString(
            (string) $draft->thumbnail_path,
            $catalog,
            'A draft course and its cover are in the public catalog.'
        );
    }

    /* -------------------------------------------------------- the action itself */

    public function test_the_action_refuses_a_photograph_outside_the_catalog_even_when_called_directly(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $this->expectException(\InvalidArgumentException::class);

        app(SetCourseCover::class)->choosePhotograph($instructor, $course, 'photo-not-in-the-catalog');
    }

    public function test_the_action_refuses_an_instructor_who_does_not_own_the_course(): void
    {
        $owner = $this->instructor();
        $stranger = $this->instructor();
        $course = $this->courseFor($owner);

        $this->expectException(AuthorizationException::class);

        app(SetCourseCover::class)->choosePhotograph($stranger, $course, $this->firstPhotograph());
    }

    public function test_the_policy_allows_an_owner_and_refuses_anybody_else(): void
    {
        $owner = $this->instructor();
        $stranger = $this->instructor();
        $student = User::factory()->create();
        $course = $this->courseFor($owner);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $course));
        $this->assertFalse(Gate::forUser($stranger)->allows('update', $course));
        $this->assertFalse(Gate::forUser($student)->allows('update', $course));
    }

    /* ------------------------------------------------------- the catalog itself */

    public function test_every_photograph_on_offer_is_one_the_catalog_holds(): void
    {
        foreach (CourseCoverCatalog::identifiers() as $identifier) {
            $this->assertTrue(
                CourseCoverCatalog::offers($identifier),
                "$identifier is in the catalog and offers() does not agree."
            );
            $this->assertStringStartsWith('photo-', $identifier, 'A photograph identifier that is not one is not an Unsplash address.');
        }
    }

    public function test_every_photograph_on_offer_describes_what_it_shows(): void
    {
        foreach (CourseCoverCatalog::SUBJECTS as $subject => $photographs) {
            $this->assertNotEmpty($photographs, "The subject $subject offers no photographs.");

            foreach ($photographs as $entry) {
                $this->assertCount(3, $entry, "An entry in $subject is not an identifier, a description and a keyword.");
                $this->assertNotSame('', $entry[0], "A photograph in $subject has no identifier.");
                $this->assertStringStartsWith('photo-', $entry[0]);
                $this->assertNotSame('', $entry[1], "A photograph in $subject has no description, so nothing could be written about it for a reader who cannot see it.");
                $this->assertNotSame('', $entry[2], "A photograph in $subject has no keyword, so the picker cannot narrow to it.");
            }
        }
    }

    public function test_the_address_built_for_a_photograph_carries_the_size_it_was_asked_for(): void
    {
        $identifier = $this->firstPhotograph();

        $small = CourseCoverCatalog::urlFor($identifier, 320, 180);
        $large = CourseCoverCatalog::urlFor($identifier, 1200, 400);

        $this->assertStringContainsString('w=320', $small);
        $this->assertStringContainsString('h=180', $small);
        $this->assertStringContainsString('w=1200', $large);
        $this->assertStringContainsString('h=400', $large);
        $this->assertStringContainsString('fit=crop', $large, 'Without a crop two covers of different shapes are different heights.');
        $this->assertStringContainsString('auto=format', $large, 'The browser is not left to choose the format, so every visitor downloads a JPEG.');
    }

    public function test_an_cover_url_never_contains_an_api_credential(): void
    {
        /*
         | The access key must not reach the page.
         |
         | Unsplash's own guidance is that the key belongs on a server, and this
         | application has none to leak, which is the strongest form of the guarantee.
         | The test is here so that stays true: if a key were ever added to the
         * configuration and an address were ever built with it, every page that
         | shows a cover would publish it to anybody who asked.
         */
        $url = CourseCoverCatalog::urlFor($this->firstPhotograph(), 640, 360);

        $this->assertStringNotContainsString('client_id', $url);
        $this->assertStringNotContainsString('key', strtolower($url));

        $key = config('services.unsplash.key');

        if (filled($key)) {
            $this->assertStringNotContainsString((string) $key, $url, 'The access key is in an address that goes into the page.');
        }

        $this->assertNull(
            config('services.unsplash.key'),
            'An Unsplash access key is configured. It must stay on a server, and this application has no call to a server.'
        );
    }

    public function test_a_cover_cannot_be_stored_for_a_course_that_was_never_saved(): void
    {
        /*
         | The first version of this test called the storage service with a course
         | that had never been saved and asserted the path did not contain the id
         | zero. It did contain it, and the assertion failed: the service built
         | "course-covers/0/<uuid>.png" and stored the file there.
         |
         | Nothing in the application reaches that, because the route binds a saved
         | course. It is still worth refusing rather than leaving to that, since a
         | service that will quietly file a file under zero is one that will file a
         | lesson file under zero the first time somebody reuses it, and "the caller
         | always has an id" is not a property a service should need from its callers
         | in order to write a correct path.
         */
        $unsaved = new Course(['title' => 'Not saved yet']);
        $unsaved->instructor_id = $this->instructor()->id;

        $this->expectException(\InvalidArgumentException::class);

        app(CourseCoverStorage::class)->store($this->aRealImage(), $unsaved);
    }

    public function test_a_stored_cover_is_filed_under_its_own_course(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $stored = app(CourseCoverStorage::class)->store($this->aRealImage(), $course);

        $this->assertStringStartsWith('course-covers/'.$course->id.'/', $stored['path']);
    }
}
