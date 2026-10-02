<?php

namespace Tests\Feature\Assignments;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Support\StatusLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A student hands in work, an instructor marks it.
 *
 * THE LOOP, END TO END
 *
 * The first test walks the whole path a real person takes: an instructor sets
 * work, a student reads it, uploads, sees "waiting for checking", and then sees a
 * mark. Every later test narrows one step of that, because a feature is not
 * proven by its parts working and failing to prove that they compose.
 *
 * WHAT THESE TESTS ARE FOR, BESIDES THE FEATURE
 *
 * Two of them exist purely to pin down a sentence. "Submitted, waiting for
 * checking or scoring" is wording the user asked for by name, and wording asked
 * for by name is exactly the kind that quietly disappears in a refactor, because
 * no test fails when a banner is reworded into "Pending".
 */
class AssignmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private User $otherInstructor;

    private Course $course;

    private Module $module;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->instructor = User::factory()->instructor()->create();
        $this->student = User::factory()->create();
        $this->otherInstructor = User::factory()->instructor()->create();

        $this->course = Course::factory()->for($this->instructor, 'instructor')->create([
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
            'price_minor' => 0,
        ]);

        $this->module = Module::factory()->for($this->course, 'course')->create([
            'status' => ContentStatus::Published,
        ]);

        $this->lesson = Lesson::factory()->for($this->module, 'module')->create([
            'status' => ContentStatus::Published,
        ]);

        Enrollment::factory()->active()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);
    }

    /**
     * An instructor publishes work; a student hands in; the instructor marks it.
     */
    public function test_a_student_can_hand_in_work_and_be_marked(): void
    {
        // --- the instructor sets work ---
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $this->lesson]), [
                'title' => 'Week 3 reflection',
                'instructions' => 'Write 300 words on how normalisation differs from a lookup table.',
                'form_url' => 'https://docs.google.com/forms/d/e/abc123/viewform',
                'max_score' => 50,
                'publish' => '1',
            ])
            ->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();

        $this->assertSame(Assignment::PUBLISHED, $assignment->status);
        $this->assertSame(50, $assignment->max_score);

        // --- the student reads it ---
        $this->actingAs($this->student)
            ->get(route('student.assignments.show', [$this->course, $this->lesson, $assignment]))
            ->assertOk()
            ->assertSee('Week 3 reflection')
            ->assertSee('normalisation differs', false);

        // --- and hands in ---
        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('reflection.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('student.assignments.show', [$this->course, $this->lesson, $assignment]));

        $submission = AssignmentSubmission::query()->firstOrFail();

        $this->assertSame(AssignmentSubmission::PENDING, $submission->status);
        $this->assertNull($submission->score);
        $this->assertSame($this->student->id, $submission->student_id);

        // --- and sees that it is waiting, in the words they were promised ---
        $this->actingAs($this->student)
            ->get(route('student.assignments.show', [$this->course, $this->lesson, $assignment]))
            ->assertOk()
            ->assertSee('Submitted, waiting for checking');

        // --- the instructor marks it ---
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.submissions.grade', [$this->course, $submission]), [
                'intent' => 'grade',
                'score' => 42,
                'feedback' => 'Good on the second normal form. Push the explanation of dependency further.',
            ])
            ->assertRedirect(route('instructor.courses.assignments.show', [$this->course, $assignment]));

        $this->assertSame(AssignmentSubmission::GRADED, $submission->fresh()->status);
        $this->assertSame('42.00', $submission->fresh()->score);
        $this->assertSame($this->instructor->id, $submission->fresh()->graded_by);

        // --- and the student sees the mark and the note ---
        $this->actingAs($this->student)
            ->get(route('student.assignments.show', [$this->course, $this->lesson, $assignment]))
            ->assertOk()
            ->assertSee('42')
            ->assertSee('Good on the second normal form.', false);
    }

    /**
     * Handing back puts the work back in the queue.
     *
     * This is the reason `returned` is a state rather than a message. A student
     * who was told to try again must appear to the instructor as waiting, or the
     * queue quietly empties while a student is still working on it.
     */
    public function test_returned_work_goes_back_to_pending_when_the_student_re_submits(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'max_score' => 10,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.submissions.grade', [$this->course, $submission]), [
                'intent' => 'return',
                'feedback' => 'You described what you did but not why. Add the reasoning.',
            ])
            ->assertRedirect();

        $this->assertSame(AssignmentSubmission::RETURNED, $submission->fresh()->status);
        $this->assertNull($submission->fresh()->score);

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('second-try.pdf', 90, 'application/pdf'),
            ])
            ->assertRedirect();

        $fresh = $submission->fresh();

        $this->assertSame(AssignmentSubmission::PENDING, $fresh->status);
        $this->assertSame(1, AssignmentSubmission::query()->count(), 're-submitting must not add a second row');
        $this->assertNull($fresh->score, 'a mark from the first attempt must not survive the second');
        $this->assertNull($fresh->feedback, 'a note about the old attempt must not sit on the new file');
    }

    /**
     * A hand-back with no reason is refused.
     */
    public function test_returning_work_requires_a_reason(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.submissions.grade', [$this->course, $submission]), [
                'intent' => 'return',
            ])
            ->assertSessionHasErrors('feedback');

        $this->assertSame(AssignmentSubmission::PENDING, $submission->fresh()->status);
    }

    /**
     * The mark is checked against this assignment's own scale.
     *
     * Not against a constant. A brief marked out of 5 and a brief marked out of
     * 100 are both ordinary, and a rule written as `max:100` would refuse the
     * second and quietly accept a nonsense number against the first.
     */
    public function test_a_mark_above_the_assignment_scale_is_refused(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'max_score' => 5,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.submissions.grade', [$this->course, $submission]), [
                'intent' => 'grade',
                'score' => 6,
            ])
            ->assertSessionHasErrors('score');

        $this->assertSame(AssignmentSubmission::PENDING, $submission->fresh()->status);
    }

    /**
     * Nothing can be marked against a brief with no scale.
     *
     * The interface says so rather than offering a box that cannot work, and this
     * asserts the server agrees with the interface instead of trusting it.
     */
    public function test_nothing_can_be_marked_without_a_scale(): void
    {
        $assignment = Assignment::factory()->withoutMarkScale()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.submissions.grade', [$this->course, $submission]), [
                'intent' => 'grade',
                'score' => 10,
            ])
            ->assertSessionHasErrors('score');

        $this->assertSame(AssignmentSubmission::PENDING, $submission->fresh()->status);
    }

    /**
     * A student cannot read a brief that was never released.
     */
    public function test_a_draft_assignment_is_not_reachable_by_a_student(): void
    {
        $assignment = Assignment::factory()->draft()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.assignments.show', [$this->course, $this->lesson, $assignment]))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('answer.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    /**
     * A student who is not enrolled has nothing to read and nothing to submit.
     */
    public function test_a_student_who_is_not_enrolled_gets_nothing(): void
    {
        $outsider = User::factory()->create();

        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($outsider)
            ->get(route('student.assignments.show', [$this->course, $this->lesson, $assignment]))
            ->assertNotFound();

        $this->actingAs($outsider)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('answer.pdf', 10, 'application/pdf'),
            ])
            ->assertNotFound();

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    /**
     * An enrollment awaiting payment does not grant access.
     *
     * `grantsAccess()` names two states that do, and this is the one that must
     * not: a student who has started paying is not yet in the course.
     */
    public function test_an_enrollment_awaiting_payment_does_not_grant_access(): void
    {
        $student = User::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $this->course->id,
            'status' => EnrollmentStatus::PendingPayment,
        ]);

        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($student)
            ->get(route('student.assignments.show', [$this->course, $this->lesson, $assignment]))
            ->assertNotFound();
    }

    /**
     * An instructor from another course cannot mark this work.
     */
    public function test_another_instructor_cannot_reach_the_queue_or_the_mark(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->otherInstructor)
            ->get(route('instructor.courses.assignments.show', [$this->course, $assignment]))
            ->assertForbidden();

        $this->actingAs($this->otherInstructor)
            ->get(route('instructor.courses.assignments.submissions.show', [$this->course, $submission]))
            ->assertForbidden();

        $this->actingAs($this->otherInstructor)
            ->post(route('instructor.courses.assignments.submissions.grade', [$this->course, $submission]), [
                'intent' => 'grade',
                'score' => 100,
            ])
            ->assertForbidden();

        $this->assertSame(AssignmentSubmission::PENDING, $submission->fresh()->status);
    }

    /**
     * A student cannot read another student's hand-in, or mark anything.
     */
    public function test_a_student_cannot_reach_another_students_work(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $someoneElses = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.assignments.submissions.download', $someoneElses))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('instructor.assignments.submissions.download', $someoneElses))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->post(route('instructor.courses.assignments.submissions.grade', [$this->course, $someoneElses]), [
                'intent' => 'grade',
                'score' => 100,
            ])
            ->assertForbidden();

        $this->assertSame(AssignmentSubmission::PENDING, $someoneElses->fresh()->status);
    }

    /**
     * A student cannot mark their own work.
     *
     * The case a policy written as "the owner of the submission may do anything to
     * the submission" would pass, and it is the whole reason `grade` is a separate
     * ability from `downloadOwnSubmission`.
     */
    public function test_a_student_cannot_mark_their_own_work(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->student)
            ->post(route('instructor.courses.assignments.submissions.grade', [$this->course, $submission]), [
                'intent' => 'grade',
                'score' => 100,
            ])
            ->assertForbidden();

        $this->assertSame(AssignmentSubmission::PENDING, $submission->fresh()->status);
    }

    /**
     * An administrator cannot read a student's work.
     *
     * Administration is not teaching. An administrator can see that work exists
     * and who wrote it through the course, and has no place in the conversation
     * between an instructor and a marked answer.
     */
    public function test_an_administrator_cannot_download_a_students_work(): void
    {
        $administrator = User::factory()->create(['id' => 1]);
        $administrator->profile->forceFill(['role' => UserRole::Administrator])->save();

        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('instructor.assignments.submissions.download', $submission))
            ->assertForbidden();
    }

    /**
     * A submitted file is stored under a generated name, never the one sent.
     */
    public function test_the_stored_path_does_not_contain_the_students_filename(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('../../escape.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $submission = AssignmentSubmission::query()->firstOrFail();

        $this->assertStringNotContainsString('..', $submission->storage_path);
        $this->assertStringStartsWith('assignment-submissions/', $submission->storage_path);
        $this->assertStringNotContainsString('escape', $submission->storage_path);

        // The name the student chose is kept, because it is a label the instructor
        // reads. It is never a path.
        $this->assertStringNotContainsString('..', $submission->original_name);
    }

    /**
     * The extension list is enforced, and the contents are checked rather than the
     * name. A `.php` file renamed to `.pdf` is not a PDF.
     */
    public function test_a_file_that_is_not_what_it_claims_is_refused(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        /*
         | A real file, not `UploadedFile::fake()`.
         |
         | `fake()` hands back whatever media type it was told to hand back, so a
         | fake `essay.pdf` reports `application/pdf` no matter what is inside it and
         | the `mimetypes` rule never reads a byte. A test written with `fake()`
         | here would pass against a server that validated nothing, which is the
         | worst kind of green.
         |
         | So this writes the bytes to a real temporary file and lets finfo read
         | them, which is the same path a browser upload takes.
         */
        $path = tempnam(sys_get_temp_dir(), 'submission').'.pdf';
        file_put_contents($path, "<?php echo 'this is not a document'; ?>");

        $real = new UploadedFile($path, 'essay.pdf', null, null, true);

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => $real,
            ])
            ->assertSessionHasErrors('submission');

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    /**
     * An executable is refused whatever it is called.
     */
    public function test_a_script_cannot_be_handed_in(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('shell.php', 4, 'text/plain'),
            ])
            ->assertSessionHasErrors('submission');

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    /**
     * A Google Form address is required to be one.
     *
     * The check is the shape of the address and nothing more. Whether the form
     * exists is not knowable from here, and a check that pretended to be would
     * pass on any URL with the right words in it.
     */
    public function test_the_form_link_must_be_a_google_form(): void
    {
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $this->lesson]), [
                'title' => 'Anything at all',
                'instructions' => 'Answer the questions in the form linked below this one.',
                'form_url' => 'https://evil.example.com/forms/not-a-google-form',
            ])
            ->assertSessionHasErrors('form_url');

        $this->assertSame(0, Assignment::query()->count());
    }

    /**
     * Instructions alone are a complete brief.
     *
     * An instructor should not be made to attach a document or a link to set work
     * they have already described in prose.
     */
    public function test_text_instructions_alone_are_enough(): void
    {
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $this->lesson]), [
                'title' => 'Just words',
                'instructions' => 'Explain what a foreign key constraint prevents, in your own words.',
                'publish' => '1',
            ])
            ->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();

        $this->assertNull($assignment->form_url);
        $this->assertNull($assignment->briefing_path);
        $this->assertNull($assignment->max_score, 'no scale is allowed; only marking needs one');
    }

    /**
     * An instructor cannot set work on somebody else's course.
     */
    public function test_an_instructor_cannot_set_work_on_a_course_they_do_not_own(): void
    {
        $this->actingAs($this->otherInstructor)
            ->get(route('instructor.courses.assignments.create', [$this->course, $this->lesson]))
            ->assertForbidden();

        $this->actingAs($this->otherInstructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $this->lesson]), [
                'title' => 'Not mine to set',
                'instructions' => 'This should never be stored anywhere at all.',
            ])
            ->assertForbidden();

        $this->assertSame(0, Assignment::query()->count());
    }

    /**
     * A student cannot reach an instructor page at all.
     */
    public function test_a_student_cannot_reach_the_instructor_pages(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($this->student)
            ->get(route('instructor.courses.assignments.create', [$this->course, $this->lesson]))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('instructor.courses.assignments.show', [$this->course, $assignment]))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->patch(route('instructor.courses.assignments.status', [$this->course, $assignment]), [
                'publish' => '1',
            ])
            ->assertForbidden();

        $this->assertSame(Assignment::PUBLISHED, $assignment->fresh()->status);
    }

    /**
     * The queue is ordered oldest first.
     *
     * Not newest first, and this asserts the order rather than the contents,
     * because "which one do I do first" is the only question the list is answering.
     */
    public function test_the_queue_is_ordered_oldest_first(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $older = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => User::factory()->create()->id,
            'original_name' => 'older.pdf',
            'submitted_at' => now()->subHours(3),
        ]);

        $newer = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => User::factory()->create()->id,
            'original_name' => 'newer.pdf',
            'submitted_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->instructor)
            ->get(route('instructor.courses.assignments.show', [$this->course, $assignment]))
            ->assertOk();

        $body = $response->getContent();

        $this->assertLessThan(
            strpos($body, 'newer.pdf'),
            (int) strpos($body, 'older.pdf'),
            'the older hand-in must appear before the newer one'
        );

        $this->assertTrue($older->isPending());
        $this->assertTrue($newer->isPending());
    }

    /**
     * Marked work leaves the top of the queue.
     */
    public function test_marked_work_is_no_longer_in_the_queue(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'max_score' => 100,
        ]);

        $waiting = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
            'original_name' => 'waiting.pdf',
        ]);

        AssignmentSubmission::factory()->graded(90)->create([
            'assignment_id' => $assignment->id,
            'student_id' => User::factory()->create()->id,
            'original_name' => 'already-done.pdf',
        ]);

        $body = $this->actingAs($this->instructor)
            ->get(route('instructor.courses.assignments.show', [$this->course, $assignment]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('waiting.pdf', $body);
        $this->assertStringContainsString('already-done.pdf', $body);

        $waitingHeading = strpos($body, 'Waiting for you');
        $doneHeading = strpos($body, 'Already looked at');

        $this->assertNotFalse($waitingHeading);
        $this->assertNotFalse($doneHeading);
        $this->assertLessThan($doneHeading, $waitingHeading, 'pending work comes first on the page');

        $this->assertLessThan(
            strpos($body, 'already-done.pdf'),
            (int) strpos($body, 'waiting.pdf'),
            'a marked submission must not sit above a waiting one'
        );
    }

    /**
     * A closed assignment takes no more work, and says 410 rather than 404.
     *
     * "Gone" is the wrong word: the brief is still there, and the honest answer is
     * that this address exists and will not accept anything more.
     */
    public function test_a_closed_assignment_refuses_a_hand_in(): void
    {
        $assignment = Assignment::factory()->closed()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(410);

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    /**
     * A past date refuses a hand-in too.
     */
    public function test_a_past_date_refuses_a_hand_in(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'due_at' => now()->subMinute(),
        ]);

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    /**
     * A download serves the bytes with the right headers.
     */
    public function test_an_instructor_can_download_the_file_behind_a_hand_in(): void
    {
        Storage::disk('local')->put('assignment-submissions/test/answer.pdf', 'the real bytes');

        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
            'storage_path' => 'assignment-submissions/test/answer.pdf',
            'mime_type' => 'application/pdf',
            'original_name' => 'my essay.pdf',
        ]);

        $response = $this->actingAs($this->instructor)
            ->get(route('instructor.assignments.submissions.download', $submission))
            ->assertOk();

        $this->assertSame('the real bytes', $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        // Symfony rewrites Cache-Control into its own canonical directive order,
        // so this asserts on the directives rather than on the literal string.
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    /**
     * A filename that would break the header is not echoed back.
     *
     * The name a student types is theirs, and `Content-Disposition` is a header
     * value. A quote or a newline in there lets the sender choose part of the
     * response, so only the extension survives and the stem is ours.
     */
    public function test_a_hostile_filename_cannot_escape_the_download_header(): void
    {
        Storage::disk('local')->put('assignment-submissions/test/answer.pdf', 'bytes');

        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
            'storage_path' => 'assignment-submissions/test/answer.pdf',
            'original_name' => 'evil"; filename="admin.pdf.pdf',
        ]);

        $response = $this->actingAs($this->instructor)
            ->get(route('instructor.assignments.submissions.download', $submission))
            ->assertOk();

        $disposition = (string) $response->headers->get('Content-Disposition');

        $this->assertSame('attachment; filename="submission.pdf"', $disposition);
    }

    /**
     * A record whose file is missing is a 404, not an empty 200.
     *
     * An empty download that reports success is a failure an instructor will read
     * as a mark of zero.
     */
    public function test_a_missing_file_is_a_not_found(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
            'storage_path' => 'assignment-submissions/gone/deleted.pdf',
        ]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.assignments.submissions.download', $submission))
            ->assertNotFound();

        $this->actingAs($this->student)
            ->get(route('student.assignments.submissions.download', $submission))
            ->assertNotFound();
    }

    /**
     * An assignment with no briefing is a 404 on the briefing link, not an error.
     */
    public function test_an_assignment_with_no_briefing_has_no_briefing_to_download(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.assignments.briefing.download', $assignment))
            ->assertNotFound();

        $this->actingAs($this->student)
            ->get(route('student.assignments.briefing', $assignment))
            ->assertNotFound();
    }

    /**
     * The address must agree with the record.
     *
     * A brief that belongs to one lesson cannot be read through another lesson's
     * address, which is the check that stops the course and lesson in the path
     * being decoration.
     */
    public function test_an_address_that_disagrees_with_the_record_is_a_not_found(): void
    {
        $otherLesson = Lesson::factory()->for($this->module, 'module')->create([
            // `lessons` is unique on (module, position), and the lesson built in setUp
            // already holds position 1 on this module.
            'position' => 2,
            'status' => ContentStatus::Published,
        ]);

        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.assignments.show', [$this->course, $otherLesson, $assignment]))
            ->assertNotFound();

        $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $otherLesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('answer.pdf', 10, 'application/pdf'),
            ])
            ->assertNotFound();

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.assignments.show', [$this->course, $assignment]))
            ->assertOk();
    }

    /**
     * Publishing a lesson is a precondition for publishing work on it.
     *
     * A published assignment on a draft lesson is reachable by nobody, so the
     * form refuses the combination instead of quietly creating it.
     */
    public function test_work_cannot_be_published_on_a_draft_lesson(): void
    {
        $draftLesson = Lesson::factory()->for($this->module, 'module')->create([
            'status' => ContentStatus::Draft,
            // See above: position 1 on this module is already taken.
            'position' => 3,
        ]);

        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $draftLesson]), [
                'title' => 'Nobody can reach this',
                'instructions' => 'This lesson has never been released to anybody.',
                'publish' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Assignment::query()->where('status', Assignment::PUBLISHED)->count());

        // A draft assignment on a draft lesson is fine, because a draft is
        // somebody's own note to themselves.
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $draftLesson]), [
                'title' => 'A note to myself',
                'instructions' => 'Work out what order these three lessons should be taught in.',
            ])
            ->assertRedirect();

        $this->assertSame(1, Assignment::query()->where('status', Assignment::DRAFT)->count());
    }

    /**
     * Closing does not delete, and the work stays readable.
     */
    public function test_closing_an_assignment_keeps_the_work_already_handed_in(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        AssignmentSubmission::factory()->graded(70)->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->instructor)
            ->patch(route('instructor.courses.assignments.status', [$this->course, $assignment]))
            ->assertRedirect();

        $this->assertSame(Assignment::CLOSED, $assignment->fresh()->status);
        $this->assertSame(1, AssignmentSubmission::query()->count());

        // The student can still read the brief and the mark they were given.
        $this->actingAs($this->student)
            ->get(route('student.assignments.show', [$this->course, $this->lesson, $assignment]))
            ->assertOk()
            ->assertSee('Closed to new submissions');
    }

    /**
     * A closed assignment cannot be closed twice, or re-published.
     *
     * `update` refuses a closed assignment, and the reason is that reopening one
     * after work has been handed in makes those answers describe a question that
     * was withdrawn and then quietly restored.
     */
    public function test_a_closed_assignment_cannot_be_reopened(): void
    {
        $assignment = Assignment::factory()->closed()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $this->actingAs($this->instructor)
            ->patch(route('instructor.courses.assignments.status', [$this->course, $assignment]), [
                'publish' => '1',
            ])
            ->assertForbidden();

        $this->assertSame(Assignment::CLOSED, $assignment->fresh()->status);
    }

    /**
     * Publishing is decided by the checkbox, not by a field nobody renders.
     */
    public function test_a_status_in_the_request_cannot_publish_an_assignment(): void
    {
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $this->lesson]), [
                'title' => 'Sneaky',
                'instructions' => 'This request carries a status field that no form on screen sends.',
                'status' => Assignment::PUBLISHED,
                'lesson_id' => $this->lesson->id,
            ])
            ->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();

        $this->assertSame(Assignment::DRAFT, $assignment->status);
        $this->assertSame($this->lesson->id, $assignment->lesson_id);
    }

    /**
     * Two assignments with the same title on one lesson is refused before the
     * database has to refuse it.
     */
    public function test_the_same_title_cannot_be_used_twice_on_one_lesson(): void
    {
        Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'title' => 'Week 3 reflection',
        ]);

        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $this->lesson]), [
                'title' => 'Week 3 reflection',
                'instructions' => 'The same title again, which is nearly always a double submission.',
            ])
            ->assertSessionHasErrors('title');

        $this->assertSame(1, Assignment::query()->count());
    }

    /**
     * The same title on a different lesson is fine.
     */
    public function test_the_same_title_on_a_different_lesson_is_allowed(): void
    {
        $otherLesson = Lesson::factory()->for($this->module, 'module')->create([
            'status' => ContentStatus::Published,
            // See above: position 1 on this module is already taken.
            'position' => 2,
        ]);

        Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'title' => 'Reflection',
        ]);

        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $otherLesson]), [
                'title' => 'Reflection',
                'instructions' => 'The same title on a different lesson is a different piece of work.',
            ])
            ->assertRedirect();

        $this->assertSame(2, Assignment::query()->count());
    }

    /**
     * Instructions have to say something.
     *
     * `min:10` is a low bar and it exists because a one-word brief is not a brief
     * and a student cannot be expected to answer it.
     */
    public function test_instructions_cannot_be_empty_or_a_single_word(): void
    {
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $this->lesson]), [
                'title' => 'Too thin',
                'instructions' => 'Do it',
            ])
            ->assertSessionHasErrors('instructions');

        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $this->lesson]), [
                'title' => 'Also too thin',
            ])
            ->assertSessionHasErrors('instructions');

        $this->assertSame(0, Assignment::query()->count());
    }

    /**
     * A briefing file is stored under a generated path.
     */
    public function test_a_briefing_is_stored_on_the_private_disk_under_a_generated_path(): void
    {
        $this->actingAs($this->instructor)
            ->post(route('instructor.courses.assignments.store', [$this->course, $this->lesson]), [
                'title' => 'Has a briefing',
                'instructions' => 'The longer version of the brief is attached below as a document.',
                'briefing' => UploadedFile::fake()->create('week-3-briefing-draft.pdf', 60, 'application/pdf'),
                'publish' => '1',
            ])
            ->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();

        $this->assertSame('local', $assignment->briefing_disk);
        $this->assertStringStartsWith('assignment-briefings/', (string) $assignment->briefing_path);
        $this->assertStringNotContainsString('week-3', (string) $assignment->briefing_path);
        $this->assertStringNotContainsString('.pdf.pdf', (string) $assignment->briefing_path);
        $this->assertSame('application/pdf', $assignment->briefing_mime_type);

        Storage::disk('local')->assertExists($assignment->briefing_path);

        // And it is not on the public disk, which is the one a web server serves.
        Storage::disk('public')->assertMissing($assignment->briefing_path);
    }

    /**
     * A student with access can read the briefing; one without cannot.
     */
    public function test_the_briefing_needs_course_access(): void
    {
        Storage::disk('local')->put('assignment-briefings/test/brief.pdf', 'briefing bytes');

        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'briefing_disk' => 'local',
            'briefing_path' => 'assignment-briefings/test/brief.pdf',
            'briefing_mime_type' => 'application/pdf',
        ]);

        $this->actingAs($this->student)
            ->get(route('student.assignments.briefing', $assignment))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->instructor)
            ->get(route('instructor.assignments.briefing.download', $assignment))
            ->assertOk();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)
            ->get(route('student.assignments.briefing', $assignment))
            ->assertForbidden();
    }

    /**
     * A draft's briefing is not readable by a student.
     *
     * The instructor can still read their own. Ownership and publication are two
     * different questions and the ability answers both.
     */
    public function test_a_drafts_briefing_is_instructors_only(): void
    {
        Storage::disk('local')->put('assignment-briefings/test/brief.pdf', 'bytes');

        $assignment = Assignment::factory()->draft()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'briefing_disk' => 'local',
            'briefing_path' => 'assignment-briefings/test/brief.pdf',
        ]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.assignments.briefing.download', $assignment))
            ->assertOk();

        $this->actingAs($this->student)
            ->get(route('student.assignments.briefing', $assignment))
            ->assertForbidden();
    }

    /**
     * A percentage is derived from the mark and the scale, never stored.
     *
     * Storing both invites them to disagree, and this asserts they cannot: the
     * same row with a different scale produces a different percentage and the same
     * stored value.
     */
    public function test_a_percentage_is_derived_and_never_stored(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'max_score' => 20,
        ]);

        $submission = AssignmentSubmission::factory()->graded(15)->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->assertSame(75, $submission->percentage());

        $assignment->forceFill(['max_score' => 60])->save();

        $this->assertSame(25, $submission->fresh()->percentage());
        $this->assertArrayNotHasKey(
            'percentage',
            $submission->fresh()->getAttributes(),
            'a derived number must not be a column'
        );
    }

    /**
     * A submission with no mark has no percentage.
     */
    public function test_an_unmarked_submission_has_no_percentage(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'max_score' => 100,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $this->assertNull($submission->percentage());
    }

    /**
     * The wording the user asked for by name.
     *
     * "Submitted, waiting for checking or scoring" is a promise about what the
     * student will see, and a promise with no test behind it is the first thing to
     * go in a rename.
     */
    public function test_the_waiting_wording_is_the_wording_that_was_asked_for(): void
    {
        $this->assertSame(
            'Submitted, waiting for checking',
            StatusLabel::forSubmission(AssignmentSubmission::PENDING)['label'],
        );

        $this->assertSame(
            'Checked and scored',
            StatusLabel::forSubmission(AssignmentSubmission::GRADED)['label'],
        );

        $this->assertSame(
            'Handed back to be redone',
            StatusLabel::forSubmission(AssignmentSubmission::RETURNED)['label'],
        );

        // And the flash message after handing in says the same thing.
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        $response = $this->actingAs($this->student)
            ->post(route('student.assignments.submit', [$this->course, $this->lesson, $assignment]), [
                'submission' => UploadedFile::fake()->create('answer.pdf', 10, 'application/pdf'),
            ]);

        $this->assertSame(
            'Your file was submitted. Waiting for checking or scoring.',
            $response->getSession()->get('status'),
        );
    }

    /**
     * A student finds the work from the lesson it is set on.
     *
     * The feature is built and reachable, and those are two different claims. A
     * feature nothing links to is a feature nobody finds, and it passes every test
     * in this file because each of them walks a URL directly. This one starts at
     * the lesson, which is where a student actually is.
     */
    public function test_a_student_finds_the_work_from_the_lesson(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'title' => 'Week 3 reflection',
            'max_score' => 50,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.lessons.show', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertSee('Work to hand in')
            ->assertSee('Week 3 reflection')
            ->assertSee(route('student.assignments.show', [$this->course, $this->lesson, $assignment]))
            ->assertSee('You have not handed anything in')
            ->assertSee('out of 50');
    }

    /**
     * A lesson with no work does not say there is an empty section.
     */
    public function test_a_lesson_with_no_work_has_no_work_section(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.lessons.show', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertDontSee('Work to hand in')
            ->assertDontSee('You have not handed anything in');
    }

    /**
     * A draft is not advertised to a student on the lesson.
     *
     * The relation is filtered, so this is a different check from "a draft
     * returns 403": the brief is not listed at all. A link to something that
     * answers "no" teaches the student that the page is unreliable.
     */
    public function test_a_draft_is_not_listed_on_the_lesson(): void
    {
        Assignment::factory()->draft()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'title' => 'A note to the instructor',
        ]);

        $this->actingAs($this->student)
            ->get(route('student.lessons.show', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertDontSee('A note to the instructor');
    }

    /**
     * A student sees their own state on the lesson, and nobody else's.
     */
    public function test_the_lesson_shows_this_students_state_only(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
        ]);

        AssignmentSubmission::factory()->graded(80)->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        AssignmentSubmission::factory()->graded(10)->create([
            'assignment_id' => $assignment->id,
            'student_id' => User::factory()->create()->id,
        ]);

        $body = $this->actingAs($this->student)
            ->get(route('student.lessons.show', [$this->course, $this->lesson]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('You: Checked and scored', $body);
        $this->assertStringNotContainsString('You have not handed anything in', $body);

        // The other student's row is not on the page in any form. The relation was
        // scoped in the query, so it was never loaded.
        $otherName = AssignmentSubmission::query()
            ->where('student_id', '!=', $this->student->id)
            ->with('student')
            ->first()
            ?->student
            ?->name;

        $this->assertNotNull($otherName);
        $this->assertStringNotContainsString((string) $otherName, (string) $body);
    }

    /**
     * An instructor finds the "set work" form from the course outline.
     */
    public function test_an_instructor_finds_the_form_from_the_course_outline(): void
    {
        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.show', $this->course))
            ->assertOk()
            ->assertSee('Set an assignment on this lesson')
            ->assertSee(route('instructor.courses.assignments.create', [$this->course, $this->lesson]));
    }

    /**
     * The outline says what is waiting, and says a brief cannot be marked yet.
     *
     * Both are the kind of thing that is true in the database and absent from the
     * screen, and both are why an instructor would otherwise have to open every
     * assignment to answer one question about the course.
     */
    public function test_the_outline_reports_pending_work_and_an_unmarkable_brief(): void
    {
        $waiting = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'title' => 'Needs marking',
            'max_score' => 20,
        ]);

        AssignmentSubmission::factory()->create([
            'assignment_id' => $waiting->id,
            'student_id' => $this->student->id,
        ]);

        Assignment::factory()->withoutMarkScale()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'title' => 'No scale yet',
        ]);

        $this->actingAs($this->instructor)
            ->get(route('instructor.courses.show', $this->course))
            ->assertOk()
            ->assertSee('Needs marking')
            ->assertSee('1 waiting')
            ->assertSee('No scale yet')
            ->assertSee('no mark scale yet');
    }

    /**
     * A pending count is a count and not a guess.
     */
    public function test_the_outline_does_not_count_marked_work_as_waiting(): void
    {
        $assignment = Assignment::factory()->for($this->lesson, 'lesson')->create([
            'created_by' => $this->instructor->id,
            'title' => 'All done',
            'max_score' => 20,
        ]);

        AssignmentSubmission::factory()->graded(15)->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
        ]);

        $body = $this->actingAs($this->instructor)
            ->get(route('instructor.courses.show', $this->course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('All done', $body);
        $this->assertStringNotContainsString('waiting', $body);
    }

    /**
     * A student cannot reach the instructor's outline by typing its address.
     */
    public function test_a_student_cannot_reach_the_instructor_outline(): void
    {
        $this->actingAs($this->student)
            ->get(route('instructor.courses.show', $this->course))
            ->assertForbidden();
    }
}
