<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\Assignments\AssignmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demonstration assignments, and the work handed in against them.
 *
 * WHY THIS EXISTS
 *
 * An LMS in which nobody can hand in anything and nobody can be marked cannot be
 * demonstrated. The feature is real and tested, but a demonstration needs three
 * states visible at once — waiting, marked, and handed back — because "it works"
 * and "it shows the three states correctly" are different claims, and a panel will
 * only hear the first one.
 *
 * NOTHING HERE IS A REAL STUDENT'S WORK
 *
 * Every file is a generated PDF that says on its face that it is a demonstration
 * file. Every name is a placeholder. The files are real PDFs rather than text with
 * a PDF name because the first version of this seeder wrote plain text and called
 * it `reflection.pdf`, and the marking page reported `text/plain` under a `.pdf`
 * name without complaint. Nothing was broken — a seeded row is not validated the
 * way an upload is — but a panel reading the type and the extension would notice,
 * and a demonstration should not need a footnote explaining it.
 *
 * IDEMPOTENT, ON PURPOSE
 *
 * Work is looked up by title before being created, so running this twice leaves
 * one of each rather than two. A seeder that doubled the demonstration every time
 * it ran would make the third run look like a bug.
 */
class AssignmentDemoSeeder extends Seeder
{
    /**
     * The one brief everything else hangs off.
     *
     * A Google Form and a briefing document and a mark scale and a date, because
     * the demonstration is of the whole brief rather than of its shortest version.
     * An assignment with text instructions alone is supported and is the common
     * case, but it shows one attachment route rather than all of them.
     */
    private const BRIEF_TITLE = 'Week 3 reflection: normalisation and lookup tables';

    private const BRIEF_INSTRUCTIONS = <<<'TEXT'
        Write 300 words on how normalisation differs from a lookup table.

        Say which one your last project actually needed, and what would have gone
        wrong if you had used the other one. Give one concrete example from the
        schema you built rather than describing it in general terms.

        If you answered the Google Form, submit a file here as well. Your
        instructor marks the file, so a form response on its own is not something
        that can be marked.
        TEXT;

    private const BRIEFING = [
        'Week 3 briefing - normalisation and lookup tables',
        '',
        'This is the longer version of the brief, attached as a document, because',
        'three sentences in a text box is not always the shape of the question.',
        '',
        'Your instructor marks the file you upload here, not the form response.',
        'A form collects answers; it does not leave anything to mark.',
        '',
        'Marked out of 50, on the instructor own scale. Nothing in the application',
        'converts that number to a percentage on its own.',
    ];

    /**
     * The second brief, left with no mark scale.
     *
     * Present on purpose. A brief with no scale cannot be marked, and the interface
     * says so rather than offering a box that cannot work. Showing a system
     * explaining its own limit is worth more than showing one more happy path.
     */
    private const UNMARKED_TITLE = 'Reading list, week 3 (no mark yet)';

    private const UNMARKED_INSTRUCTIONS = <<<'TEXT'
        Read the two articles below and be ready to discuss how they disagree
        about where a boundary belongs.

        This brief has no mark scale yet, so it cannot be marked. It is here to
        show that the application says so instead of quietly failing when you try.
        TEXT;

    public function run(): void
    {
        $course = $this->targetCourse();

        if ($course === null) {
            $this->command?->warn('No published course with enrolled students was found, so no assignments were seeded.');

            return;
        }

        $lesson = $course->modules
            ->flatMap(fn (Module $module) => $module->lessons)
            ->first(fn (Lesson $lesson) => $lesson->status === ContentStatus::Published);

        if ($lesson === null) {
            $this->command?->warn('The course has no published lesson, so no assignments were seeded.');

            return;
        }

        $this->seedMainAssignment($course, $lesson);
        $this->seedUnmarkableAssignment($lesson);
    }

    /**
     * The brief everything else hangs off: students waiting, one marked, one back.
     */
    private function seedMainAssignment(Course $course, Lesson $lesson): void
    {
        if (Assignment::query()->where('lesson_id', $lesson->id)->where('title', self::BRIEF_TITLE)->exists()) {
            $this->command?->info('The week 3 reflection already exists; left alone.');

            return;
        }

        $instructor = User::query()->findOrFail($course->instructor_id);
        $briefingPath = $this->writeBriefing();

        $assignment = Assignment::query()->create([
            'lesson_id' => $lesson->id,
            'created_by' => $instructor->id,
            'title' => self::BRIEF_TITLE,
            'instructions' => self::BRIEF_INSTRUCTIONS,

            /*
             | A real Google Form address shape. It is not a working form, and
             | nothing in the interface claims that it is: the page cannot know, and
             | the note under the link tells a student to submit a file too so that
             | there is something to mark either way.
             */
            'form_url' => 'https://docs.google.com/forms/d/e/1FAKEFORMIDforTheDemo/viewform',

            'briefing_disk' => AssignmentService::DISK,
            'briefing_path' => $briefingPath,
            'briefing_mime_type' => 'application/pdf',
            'briefing_byte_size' => (int) Storage::disk(AssignmentService::DISK)->size($briefingPath),

            'max_score' => 50,
            'status' => Assignment::PUBLISHED,
            'due_at' => now()->addDays(7),
        ]);

        $this->command?->info('Seeded assignment: '.$assignment->title);

        $students = $this->enrolledStudents($course);

        // Two waiting, oldest first, so the queue has more than one row in it and
        // the ordering is visible rather than asserted.
        foreach (array_slice($students, 0, 2) as $index => $student) {
            $this->seedSubmission(
                $assignment,
                $student,
                AssignmentSubmission::PENDING,
                'reflection.pdf',
                now()->subHours(30 - ($index * 6)),
            );
        }

        // One marked, so the "already looked at" half of the page has content.
        if (isset($students[2])) {
            $this->seedSubmission(
                $assignment,
                $students[2],
                AssignmentSubmission::GRADED,
                'reflection.pdf',
                now()->subHours(2),
                42,
                "Strong on the second normal form. The example from your enrollment schema is the part that carries the answer.\n\nPush the explanation of why a lookup table would have duplicated that data. You say it would repeat things, which is true and is not yet an explanation.",
                $instructor,
            );
        }

        // One handed back. The state that shows the queue is not a list of
        // everything, and that a return is a state rather than a message.
        if (isset($students[3])) {
            $this->seedSubmission(
                $assignment,
                $students[3],
                AssignmentSubmission::RETURNED,
                'first-try.pdf',
                now()->subDay(),
                null,
                'You described what you did but not why. Add the reasoning: what specifically breaks when the foreign key is missing.',
                $instructor,
            );
        }
    }

    /**
     * A brief with no mark scale, which the interface has to explain.
     */
    private function seedUnmarkableAssignment(Lesson $lesson): void
    {
        if (Assignment::query()->where('lesson_id', $lesson->id)->where('title', self::UNMARKED_TITLE)->exists()) {
            return;
        }

        Assignment::query()->create([
            'lesson_id' => $lesson->id,
            'created_by' => User::query()->whereHas('ownedCourses')->value('id'),
            'title' => self::UNMARKED_TITLE,
            'instructions' => self::UNMARKED_INSTRUCTIONS,
            'form_url' => null,
            'max_score' => null,
            'status' => Assignment::PUBLISHED,
            'due_at' => null,
        ]);
    }

    /**
     * One hand-in, written as a real PDF and stored under a generated name.
     */
    private function seedSubmission(
        Assignment $assignment,
        User $student,
        string $state,
        string $filename,
        \DateTimeInterface $submittedAt,
        ?int $score = null,
        ?string $feedback = null,
        ?User $instructor = null,
    ): void {
        $path = 'assignment-submissions/demo/'.Str::random(40).'.pdf';

        Storage::disk(AssignmentService::DISK)->put($path, $this->pdf([
            'DEMONSTRATION FILE. Not a real student answer.',
            '',
            'Assignment: '.$assignment->title,
            'Student: '.$student->name,
            'State: '.$state,
            '',
            'A real hand-in is a PDF, a Word document, a plain text file, or a picture',
            'of one of those. This file is a real PDF so that the type recorded against',
            'it and the name it was given agree, which is the sort of small thing that',
            'is worth getting right in a demonstration and not worth noticing.',
        ]));

        AssignmentSubmission::query()->updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            [
                'storage_disk' => AssignmentService::DISK,
                'storage_path' => $path,
                'original_name' => $this->filename($student, $filename),
                'mime_type' => 'application/pdf',
                'byte_size' => (int) Storage::disk(AssignmentService::DISK)->size($path),
                'status' => $state,
                'score' => $score,
                'feedback' => $feedback,
                'graded_by' => $state === AssignmentSubmission::PENDING ? null : $instructor?->id,
                'graded_at' => $state === AssignmentSubmission::PENDING ? null : now(),
                'submitted_at' => $submittedAt,
            ],
        );
    }

    /**
     * A real PDF on the private disk, so the briefing download serves bytes.
     */
    private function writeBriefing(): string
    {
        $path = 'assignment-briefings/demo/'.Str::random(40).'.pdf';

        Storage::disk(AssignmentService::DISK)->put($path, $this->pdf(self::BRIEFING));

        return $path;
    }

    /**
     * A filename that belongs to this student and not to the next one.
     *
     * The first version wrote `reflection-joren.pdf` for every waiting row, which
     * put the same label on two different students in one queue. Two rows reading
     * identically is the sort of thing a panel spots, and it is free to avoid.
     */
    private function filename(User $student, string $suffix): string
    {
        return Str::slug($student->name).'-'.Str::before($suffix, '.').'.pdf';
    }

    /**
     * One minimal PDF writer, used for the briefing and for every hand-in.
     *
     * Hand-written rather than pulled from a library because the project has no PDF
     * dependency, and adding one so a seeder could write a demonstration file would
     * be the wrong trade. A valid single-page PDF is about thirty lines and needs
     * nothing installed.
     */
    private function pdf(array $lines): string
    {
        $body = $this->pdfBody($lines);

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($body)." >>\nstream\n".$body.'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= ($number + 1).' 0 obj'."\n".$object."\n".'endobj'."\n";
        }

        $start = strlen($pdf);
        $count = count($objects);

        $pdf .= 'xref'."\n".'0 '.($count + 1)."\n".'0000000000 65535 f '."\n";

        for ($i = 1; $i <= $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i - 1]);
        }

        $pdf .= 'trailer'."\n".'<< /Size '.($count + 1).' /Root 1 0 R >>'."\n"
            .'startxref'."\n".$start."\n".'%%EOF'."\n";

        return $pdf;
    }

    /**
     * The page's text stream, one line of the argument per line of the page.
     */
    private function pdfBody(array $lines): string
    {
        $y = 780;
        $out = '';
        $first = true;

        foreach ($lines as $line) {
            $size = $first ? 18 : 11;
            $out .= 'BT /F1 '.$size.' Tf 60 '.$y.' Td ('.$this->pdfText($line).") Tj ET\n";
            $y -= $first ? 30 : 18;
            $first = false;
        }

        return $out;
    }

    /**
     * Escape the two characters that end a PDF string.
     */
    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /**
     * The course to demonstrate on: the first published one that has students.
     */
    private function targetCourse(): ?Course
    {
        return Course::query()
            ->where('status', CourseStatus::Published)
            ->whereHas('enrollments')
            ->with(['modules.lessons'])
            ->orderBy('id')
            ->first();
    }

    /**
     * Enrolled students, oldest account first, so the queue order is stable.
     *
     * @return list<User>
     */
    private function enrolledStudents(Course $course): array
    {
        return Enrollment::query()
            ->where('course_id', $course->id)
            ->orderBy('student_id')
            ->with('student')
            ->get()
            ->map(fn (Enrollment $enrollment) => $enrollment->student)
            ->filter(fn (?User $student) => $student !== null)
            ->values()
            ->all();
    }
}
