<?php

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\RecordNotification;
use App\Enums\NotificationType;
use App\Models\Course;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\QueryCounter;
use Tests\TestCase;

/**
 * The rules the notification core has to hold whatever raises a notice.
 *
 * The plan requires five of these: a dedup key suppresses a repeat, a null dedup
 * key never suppresses, the unread count is a count and not a load, a recipient
 * with no access receives nothing, and a notification for a rolled back
 * transaction does not exist. The fifth is covered by
 * NotificationLinkSafetyTest, which is where access is decided in this slice.
 */
class NotificationCoreTest extends TestCase
{
    use RefreshDatabase;

    private RecordNotification $record;

    protected function setUp(): void
    {
        parent::setUp();

        $this->record = new RecordNotification;
    }

    private function course(): Course
    {
        return Course::factory()->create();
    }

    /* ------------------------------------------------------------- dedup key */

    public function test_a_dedup_key_suppresses_a_repeat(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        $first = $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'You finished a lesson.',
            course: $course,
            dedupKey: 'lesson-completed:7:12',
        );

        $second = $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'You finished a lesson.',
            course: $course,
            dedupKey: 'lesson-completed:7:12',
        );

        $this->assertNotNull($first);
        $this->assertNull($second, 'The same key for the same recipient must not produce a second notice.');
        $this->assertSame(1, Notification::query()->where('user_id', $student->id)->count());
    }

    public function test_a_dedup_key_is_scoped_to_one_recipient(): void
    {
        $course = $this->course();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $a = $this->record->handle($first, NotificationType::LessonStarted, 'Started.', course: $course, dedupKey: 'lesson-started:9');
        $b = $this->record->handle($second, NotificationType::LessonStarted, 'Started.', course: $course, dedupKey: 'lesson-started:9');

        $this->assertNotNull($a);
        $this->assertNotNull($b, 'One student starting a lesson must not suppress the same notice to another student.');
        $this->assertSame(
            2,
            Notification::query()->where('dedup_key', 'lesson-started:9')->count(),
            'Both students should hold the same dedup key, and the key is scoped to one recipient rather than one row.'
        );
    }

    public function test_a_null_dedup_key_never_suppresses(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        for ($i = 0; $i < 3; $i++) {
            $this->record->handle(
                $student,
                NotificationType::LessonStarted,
                'A repeatable notice.',
                course: $course,
                dedupKey: null,
            );
        }

        $this->assertSame(3, Notification::query()->where('user_id', $student->id)->count());
    }

    public function test_a_dedup_key_still_suppresses_after_the_first_notice_was_read(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        $first = $this->record->handle($student, NotificationType::QuizFailed, 'You did not pass.', course: $course, dedupKey: 'quiz-failed:3:1');

        $first->forceFill(['read_at' => now()])->save();

        $third = $this->record->handle($student, NotificationType::QuizFailed, 'x', course: $course, dedupKey: 'quiz-failed:3:1');

        $this->assertNull($third, 'Reading a notice must not re-open its dedup key to a second copy.');
        $this->assertSame(1, Notification::query()->where('user_id', $student->id)->count());
    }

    /**
     * The suppression has to be the database's doing, not the code's.
     *
     * The seam catches a duplicate and returns null, which on its own proves
     * nothing: a check that ran first could do the same. This writes the second
     * row straight past the seam and asks the database to refuse it, which is
     * the property the plan actually relies on. It is also what makes two
     * concurrent listeners safe rather than merely careful.
     */
    public function test_the_database_itself_refuses_a_duplicate_key(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        $this->record->handle($student, NotificationType::CourseCompleted, 'Done.', course: $course, dedupKey: 'course-completed:5');

        $this->expectException(QueryException::class);

        DB::table('notifications')->insert([
            'user_id' => $student->id,
            'type' => NotificationType::CourseCompleted->value,
            'title' => 'Written straight past the seam.',
            'course_id' => $course->id,
            'dedup_key' => 'course-completed:5',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_many_null_dedup_keys_coexist_because_mysql_treats_nulls_as_distinct(): void
    {
        $student = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            DB::table('notifications')->insert([
                'user_id' => $student->id,
                'type' => NotificationType::SystemAnnouncement->value,
                'title' => 'Notice '.$i,
                'dedup_key' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(5, Notification::query()->where('user_id', $student->id)->count());
    }

    public function test_a_dedup_key_longer_than_the_column_is_refused_rather_than_truncated(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        $this->expectException(\InvalidArgumentException::class);

        // 121 characters. MySQL would shorten this to 120 and two different keys
        // sharing a prefix would then suppress each other.
        $this->record->handle(
            $student,
            NotificationType::LessonCompleted,
            'x',
            course: $course,
            dedupKey: str_repeat('a', 120).'b',
        );
    }

    /* --------------------------------------------------------- the count is a count */

    public function test_the_unread_count_is_one_count_and_does_not_load_the_rows(): void
    {
        $student = User::factory()->create();

        foreach (NotificationType::cases() as $index => $type) {
            Notification::factory()
                ->forRecipient($student)
                ->ofType($type)
                ->create(['read_at' => $index < 5 ? now() : null]);
        }

        $counter = new QueryCounter;
        $measured = $counter->measure(fn (): int => Notification::unreadCountFor($student));

        // Derived rather than written down, because the number of notices here is
        // the size of the vocabulary. Hard coding it meant that adding a type
        // broke this test as well as the one that counts the vocabulary, and the
        // second failure is noise that hides the first.
        $expected = count(NotificationType::cases()) - 5;

        $this->assertSame($expected, $measured['result']);
        $this->assertSame(1, $measured['count'], 'Drawing a badge must cost one query, not one per notice.');
        $this->assertStringContainsString('count(', strtolower($measured['queries'][0]['sql']));
    }

    public function test_the_unread_count_does_not_grow_with_the_number_of_notices(): void
    {
        $student = User::factory()->create();
        $counter = new QueryCounter;

        $costs = [];

        foreach ([10, 200] as $batch) {
            Notification::factory()->count($batch)->forRecipient($student)->create();

            $costs[] = $counter->measure(fn (): int => Notification::unreadCountFor($student))['count'];
        }

        $this->assertSame([$costs[0]], [$costs[1]], 'The badge cost must not move when the data does.');
    }

    public function test_the_unread_count_only_ever_counts_the_askers_own_notices(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        Notification::factory()->count(3)->forRecipient($mine)->create();
        Notification::factory()->count(7)->forRecipient($theirs)->create();

        $this->assertSame(3, Notification::unreadCountFor($mine));
        $this->assertSame(7, Notification::unreadCountFor($theirs));
    }

    /* ------------------------------------------------------------- transactions */

    public function test_a_notice_for_a_rolled_back_transaction_does_not_exist(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        DB::beginTransaction();

        try {
            $this->record->handle(
                $student,
                NotificationType::CourseEnrollment,
                'You are enrolled.',
                course: $course,
                dedupKey: 'enrolled:'.$course->id,
            );

            $this->assertSame(
                1,
                Notification::query()->where('dedup_key', 'enrolled:'.$course->id)->count(),
                'The row exists while the transaction is open.'
            );
        } finally {
            DB::rollBack();
        }

        $this->assertSame(
            0,
            Notification::query()->where('dedup_key', 'enrolled:'.$course->id)->count(),
            'A notice raised inside a rolled back transaction must not survive it.'
        );
    }

    public function test_a_notice_survives_a_committed_transaction(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        DB::transaction(function () use ($student, $course): void {
            $this->record->handle(
                $student,
                NotificationType::CourseEnrollment,
                'You are enrolled.',
                course: $course,
                dedupKey: 'enrolled:'.$course->id,
            );
        });

        // Scoped to this notice rather than counted across the table. Slices 3 to
        // 5 and 7 gave this table other writers, and a test that asserts on the
        // total number of rows in it now measures the automation as much as the
        // seam. The first version did exactly that and failed with a count that
        // had nothing to do with what it was checking.
        $this->assertSame(
            1,
            Notification::query()->where('dedup_key', 'enrolled:'.$course->id)->count()
        );
    }

    /* ------------------------------------------------------------------ storage */

    public function test_the_stored_text_is_a_snapshot_and_is_not_recomputed(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        $this->record->handle(
            $student,
            NotificationType::CourseCompleted,
            'You finished Web Development Fundamentals.',
            course: $course,
            dedupKey: 'finished:'.$course->id,
        );

        $course->forceFill(['title' => 'Renamed After The Fact'])->save();

        // Scoped to this notice. The first row in the table is no longer
        // necessarily this one now that the automation listeners write to it too.
        $stored = Notification::query()->where('dedup_key', 'finished:'.$course->id)->firstOrFail();

        $this->assertSame('You finished Web Development Fundamentals.', $stored->title);
    }

    public function test_every_type_in_the_vocabulary_can_be_stored(): void
    {
        $student = User::factory()->create();
        $course = $this->course();

        foreach (NotificationType::cases() as $type) {
            $stored = $this->record->handle(
                $student,
                $type,
                'A notice of type '.$type->value,
                course: $type->isCourseScoped() ? $course : null,
            );

            $this->assertNotNull($stored, "{$type->value} could not be stored.");
            $this->assertSame($type, $stored->fresh()->type);
        }

        // Eighteen, not the plan's seventeen. SupportMessage is the eighteenth,
        // added when a single NEW_MESSAGE proved unable to describe both a
        // course thread and a support thread. Every type is stored above, so this
        // count is what proves the database enum accepted all of them.
        $this->assertSame(18, count(NotificationType::cases()));
    }
}
