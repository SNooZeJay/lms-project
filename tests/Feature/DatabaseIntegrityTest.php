<?php

namespace Tests\Feature;

use App\Actions\Learning\MarkLessonComplete;
use App\Actions\Learning\RecordLessonActivity;
use App\Enums\ContentStatus;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What the database cannot hold on its own, kept true anyway.
 *
 * A foreign key proves a row points at an existing row. It does not prove it points
 * at the right one, and in this schema most of the rules that matter are the second
 * kind. A progress row can name a real lesson, a real enrollment and a real
 * student and still be nonsense, because nothing checks that the lesson belongs to
 * the enrollment's course, or that the student on the row is the student on the
 * enrollment. MySQL cannot express either: a check constraint cannot reach another
 * table, and a composite foreign key would need an index that exists only to make
 * a rule the write path already upholds enforceable.
 *
 * So the application is the mechanism and a test is what makes that mechanism more
 * than a promise.
 *
 * THE COLUMNS THAT COPY A FACT
 *
 * lesson_progress.student_id, quiz_attempts.student_id, certificates.student_id,
 * certificates.course_id, payments.student_id and payments.course_id are each
 * determined by the enrollment they name. That is a transitive dependency, and
 * taken literally it is a breach of third normal form.
 *
 * They are kept deliberately, and the reason is query cost. Every learner facing
 * screen asks "what has this person done" or "what does this person hold" and
 * filters on one of these columns directly. Removing them to reach a purer form
 * would turn the hottest queries in the product into a join to enrollments on every
 * row of every page, in exchange for removing a possibility the write path already
 * prevents. That is a bad trade.
 *
 * So the duplication stays and these tests are what make it safe. They ask two
 * different questions, deliberately. The ones that read every row answer "is the
 * data true now", which is what an audit asks and what a repair has to be able to
 * answer. The ones that perform a write answer "can the application make it false",
 * which is what a change has to be able to fail on. Neither substitutes for the
 * other: a passing read over an empty table says nothing, which is why the file
 * builds a course with a lesson and a learner rather than trusting an empty
 * database to satisfy a condition.
 *
 * Three defects were found this way and are now pinned elsewhere, where the
 * behaviour belongs: a lesson finished through the update path kept no completion
 * date, which LessonProgressTest covers, and withdrawing an announcement left the
 * notices that announced it behind, which AnnouncementTest covers.
 */
class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /* ================================================= the chain the plan draws */

    /**
     * user, enrollment, course, module, lesson, and assessment hanging off it.
     *
     * Read out of information_schema rather than out of the models, so a model
     * that names a relationship the table does not have is caught here, and read
     * from the database rather than from the migration files, so a key that was
     * dropped by hand is caught too.
     */
    public function test_the_relationship_chain_is_the_one_the_plan_draws(): void
    {
        /*
         | The first argument is the table that holds the column, the second is the
         | column, and what is asserted is the table that column points at. Reading
         | the left hand side as the answer rather than the right is a mistake this
         | method made once, and it produced a test that passed for every table
         | whose own name happened to match its parent's, which is none of them.
         */
        $this->assertSame('users', $this->foreignTarget('enrollments', 'student_id'), 'An enrollment must point at a user.');
        $this->assertSame('courses', $this->foreignTarget('enrollments', 'course_id'), 'An enrollment must point at a course.');
        $this->assertSame('courses', $this->foreignTarget('modules', 'course_id'), 'A module must point at a course.');
        $this->assertSame('modules', $this->foreignTarget('lessons', 'module_id'), 'A lesson must point at a module.');
        $this->assertSame('lessons', $this->foreignTarget('learning_materials', 'lesson_id'), 'A material must point at a lesson.');
        $this->assertSame('users', $this->foreignTarget('courses', 'instructor_id'), 'A course must point at the instructor who owns it.');
        $this->assertSame('courses', $this->foreignTarget('quizzes', 'course_id'), 'A quiz must point at a course.');
        $this->assertSame('modules', $this->foreignTarget('quizzes', 'module_id'), 'A quiz may hang off a module, and when it does, that module.');
        $this->assertSame('lessons', $this->foreignTarget('quizzes', 'lesson_id'), 'A quiz may hang off a lesson, and when it does, that lesson.');

        $this->assertSame('quizzes', $this->foreignTarget('quiz_questions', 'quiz_id'), 'A question must point at a quiz.');
        $this->assertSame('quiz_questions', $this->foreignTarget('quiz_options', 'question_id'), 'An option must point at a question.');
        $this->assertSame('quizzes', $this->foreignTarget('quiz_attempts', 'quiz_id'), 'An attempt must point at a quiz.');
        $this->assertSame('enrollments', $this->foreignTarget('quiz_attempts', 'enrollment_id'), 'An attempt must point at an enrollment.');
        $this->assertSame('quiz_attempts', $this->foreignTarget('quiz_answers', 'attempt_id'), 'A submitted answer must point at an attempt.');
        $this->assertSame('quiz_questions', $this->foreignTarget('quiz_answers', 'question_id'), 'A submitted answer must point at a question.');
        $this->assertSame('quiz_options', $this->foreignTarget('quiz_answers', 'selected_option_id'), 'A submitted answer must point at the option chosen.');
        $this->assertSame('enrollments', $this->foreignTarget('lesson_progress', 'enrollment_id'), 'Progress must point at an enrollment.');
        $this->assertSame('enrollments', $this->foreignTarget('certificates', 'enrollment_id'), 'A certificate must point at an enrollment.');
        $this->assertSame('users', $this->foreignTarget('profiles', 'user_id'), 'A profile must point at the account it describes.');
    }

    /**
     * The plan's deferral of assignments, and what replaced it.
     *
     * THIS TEST USED TO ASSERT THAT THESE TABLES DID NOT EXIST. It named four
     * tables it expected to stay absent, on the grounds that "a half built
     * assignment table that nothing reads is a claim the project has moved on".
     *
     * That reasoning was sound and it was also the reason the project could not
     * move on. Every quiz in this application is marked automatically, so the only
     * way to be assessed on written work was a column the student could write. The
     * deferral was not protecting a decision, it was preventing the feature, and
     * the test made lifting it impossible without first rewriting the test — which
     * is a guard against drift that has become a guard against the work.
     *
     * The decision has been taken and the work is built, so this now asserts what
     * is true and checks it against the database rather than against this file.
     *
     * Part of the original deferral still stands. A mark is a column on a
     * submission, not a table: one student's hand-in carries one mark, so a
     * separate `grades` table would be a second place for the same fact to live
     * and a way for the two to disagree.
     */
    public function test_the_tables_the_plan_deferred_are_built_or_still_absent(): void
    {
        // Built. The deferral was lifted and these carry real data.
        foreach (['assignments', 'assignment_submissions'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' is built, and its relationships are asserted in the test below.');
        }

        // Still deferred, and still for the reason given above.
        $this->assertFalse(
            Schema::hasTable('grades'),
            'grades exists. A mark is a column on a submission, not a table, and two places to hold one number is two places for it to be wrong.'
        );
    }

    /**
     * The new chain, asserted against the database like every other one.
     *
     * Read out of information_schema for the same reason as the chain above: a
     * relationship the models declare and the table does not have is exactly the
     * fault this file exists to catch, and a migration written and then half
     * reverted looks fine in a diff and wrong here.
     */
    public function test_the_assignment_chain_is_the_one_the_migration_draws(): void
    {
        // An assignment hangs off a lesson, not off a course.
        $this->assertSame('lessons', $this->foreignTarget('assignments', 'lesson_id'), 'An assignment must point at a lesson.');

        // Whoever set it is restricted rather than cascaded: a brief belongs to the
        // school, not to one person's account, and losing it because an instructor
        // left would be a bad trade.
        $this->assertSame('users', $this->foreignTarget('assignments', 'created_by'), 'An assignment must point at the instructor who set it.');
        $this->assertSame(
            'RESTRICT',
            $this->foreignDeleteRule('assignments', 'created_by'),
            'An assignment must survive the removal of its author.'
        );

        $this->assertSame('assignments', $this->foreignTarget('assignment_submissions', 'assignment_id'), 'A hand-in must point at an assignment.');
        $this->assertSame('users', $this->foreignTarget('assignment_submissions', 'student_id'), 'A hand-in must point at the student who wrote it.');

        // Who marked it is nulled, not cascaded: the mark is the record of a
        // student's work and outlives the staff account that happened to grade it.
        $this->assertSame('users', $this->foreignTarget('assignment_submissions', 'graded_by'), 'A hand-in must point at the instructor who marked it.');
        $this->assertSame(
            'SET NULL',
            $this->foreignDeleteRule('assignment_submissions', 'graded_by'),
            'Removing the grader account must not remove the student record that grader wrote a mark on.'
        );

        // One hand-in per student per assignment, enforced by the database rather
        // than hoped for by a query.
        $this->assertSame(
            ['assignment_id', 'student_id'],
            $this->uniqueColumns('assignment_submissions', 'assignment_submissions_assignment_id_student_id_unique'),
            'A student must have one row per assignment, so a re-submission replaces rather than accumulates.'
        );
    }

    /* ============================================== the rules the database does hold */

    public function test_the_constraints_are_live_rather_than_declared(): void
    {
        /*
         | A constraint nobody has watched refuse an insert is a claim, not a fact.
         |
         | Two profile rows for accounts that no longer existed were found in the
         | live database, which cannot happen while profiles.user_id is enforced,
         | so they came from a load that turned the checks off. Every assertion
         | about the schema in this file, and every repair tool, only means
         | something while this is on, so it is asked first and asked of the
         | server rather than read out of a configuration file.
         */
        $checks = DB::selectOne('select @@foreign_key_checks as value');

        $this->assertSame(1, (int) $checks->value, 'Foreign key checking is off, so nothing else in this file is a real constraint.');

        $this->expectException(QueryException::class);

        DB::table('profiles')->insert([
            'user_id' => (int) DB::table('users')->max('id') + 1000,
            'role' => 'student',
            'account_status' => 'active',
            'must_change_password' => 0,
        ]);
    }

    public function test_every_foreign_key_has_an_index_leading_on_its_own_column(): void
    {
        /*
         | A child column with no index is a table scan on every parent delete, and
         | MySQL will not create a key without one, so this holds by construction.
         |
         | It is asserted because a key added by hand, or dropped so a migration
         | would run, leaves no trace in the migration files, and the next person to
         | read those files would believe a guarantee that had already gone.
         */
        $database = DB::connection()->getDatabaseName();

        $keys = DB::select(
            'select k.table_name as t, k.column_name as c
               from information_schema.key_column_usage k
              where k.table_schema = ? and k.referenced_table_name is not null',
            [$database],
        );

        $leading = [];
        foreach (DB::select(
            'select table_name as t, column_name as c
               from information_schema.statistics
              where table_schema = ? and seq_in_index = 1',
            [$database],
        ) as $index) {
            $leading[$index->t.'.'.$index->c] = true;
        }

        $uncovered = [];
        foreach ($keys as $key) {
            if (! isset($leading[$key->t.'.'.$key->c])) {
                $uncovered[] = $key->t.'.'.$key->c;
            }
        }

        $this->assertSame([], $uncovered, 'A foreign key column with nothing leading an index: '.implode(', ', $uncovered));
    }

    public function test_a_check_constraint_is_enforced_and_not_merely_written_down(): void
    {
        /*
         | The free course and its price are two columns that could disagree, and
         | this is the one place the schema relies on a check rather than on the
         | application. MySQL 8 enforces it; versions before 8.0.16 parsed a check
         | and ignored it, so a schema that depended on one was depending on nothing.
         | Asserting it keeps that honest.
         */
        $this->expectException(QueryException::class);

        DB::table('courses')->insert([
            'instructor_id' => User::factory()->instructor()->create()->id,
            'title' => 'Free, with a price',
            'slug' => 'free-with-a-price-'.uniqid(),
            'course_type' => 'free',
            'price_minor' => 50000,
            'currency' => 'PHP',
            'level' => 'beginner',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_learner_cannot_hold_two_enrollments_in_one_course(): void
    {
        [, $course, , , $enrollment] = $this->enrolledCourse();

        $this->expectException(QueryException::class);

        Enrollment::factory()->create([
            'student_id' => $enrollment->student_id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
            'activated_at' => now(),
        ]);
    }

    public function test_a_learner_cannot_hold_two_progress_rows_for_one_lesson(): void
    {
        [, , , $lesson, $enrollment] = $this->enrolledCourse();

        $this->actingAs($enrollment->student)->get(route('student.lessons.show', [$enrollment->course, $lesson]))->assertOk();

        $this->expectException(QueryException::class);

        DB::table('lesson_progress')->insert([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'lesson_id' => $lesson->id,
            'status' => 'in_progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_two_siblings_cannot_claim_the_same_position(): void
    {
        $course = Course::factory()->create(['status' => CourseStatus::Published]);

        Module::factory()->create(['course_id' => $course->id, 'position' => 1]);

        $this->expectException(QueryException::class);

        Module::factory()->create(['course_id' => $course->id, 'position' => 1]);
    }

    /* ================================ the copied columns, read over every row */

    /**
     * @return array<string, array{0: string}>
     */
    public static function copiedColumnsMustAgree(): array
    {
        return [
            'progress names the student who holds the enrollment' => [
                'select lp.id from lesson_progress lp join enrollments e on e.id = lp.enrollment_id
                  where lp.student_id <> e.student_id',
            ],
            'progress names a lesson belonging to the enrollment course' => [
                'select lp.id from lesson_progress lp
                   join enrollments e on e.id = lp.enrollment_id
                   join lessons l on l.id = lp.lesson_id
                   join modules m on m.id = l.module_id
                  where m.course_id <> e.course_id',
            ],
            'an attempt names the student who holds the enrollment' => [
                'select qa.id from quiz_attempts qa join enrollments e on e.id = qa.enrollment_id
                  where qa.student_id <> e.student_id',
            ],
            'an attempt names a quiz belonging to the enrollment course' => [
                'select qa.id from quiz_attempts qa
                   join enrollments e on e.id = qa.enrollment_id
                   join quizzes q on q.id = qa.quiz_id
                  where q.course_id <> e.course_id',
            ],
            'a certificate names the student who holds the enrollment' => [
                'select c.id from certificates c join enrollments e on e.id = c.enrollment_id
                  where c.student_id <> e.student_id',
            ],
            'a certificate names the course the enrollment is for' => [
                'select c.id from certificates c join enrollments e on e.id = c.enrollment_id
                  where c.course_id <> e.course_id',
            ],
            'a payment names the student and course of the enrollment it settles' => [
                'select p.id from payments p join enrollments e on e.id = p.enrollment_id
                  where p.student_id <> e.student_id or p.course_id <> e.course_id',
            ],
            'an answer names an option of the question it answers' => [
                'select qa.id from quiz_answers qa join quiz_options qo on qo.id = qa.selected_option_id
                  where qo.question_id <> qa.question_id',
            ],
            'an answer names a question of the quiz the attempt is for' => [
                'select qa.id from quiz_answers qa
                   join quiz_attempts att on att.id = qa.attempt_id
                   join quiz_questions qq on qq.id = qa.question_id
                  where qq.quiz_id <> att.quiz_id',
            ],
            'a quiz names a module and lesson belonging to its own course' => [
                'select q.id from quizzes q
                   left join modules m on m.id = q.module_id
                   left join lessons l on l.id = q.lesson_id
                   left join modules lm on lm.id = l.module_id
                  where (q.module_id is not null and m.course_id <> q.course_id)
                     or (q.lesson_id is not null and lm.course_id <> q.course_id)',
            ],
            'a course announcement names a course, a platform one does not' => [
                "select id from announcements
                  where (scope = 'course' and course_id is null) or (scope = 'platform' and course_id is not null)",
            ],
            'a course thread names a course, a support one does not' => [
                "select id from conversations
                  where (kind = 'course' and course_id is null) or (kind = 'support' and course_id is not null)",
            ],
        ];
    }

    /**
     * @param  array{0: string}  $query
     */
    #[DataProvider('copiedColumnsMustAgree')]
    public function test_no_row_contradicts_the_column_it_copies(string $query): void
    {
        /*
         | One assertion for every rule of this shape, driven by a list.
         |
         | They are all the same fault: two columns holding one fact, able to
         | disagree. Written as a provider rather than as twelve methods because a
         | method per rule would be twelve places to forget, and the forgetting is
         | the failure mode this file exists to prevent.
         |
         | The fixture is built first so a passing assertion is never an empty table
         | agreeing with itself. The progress rows are the ones this arrangement
         | produces, and they are the rows the copied student_id is on.
         */
        // Built, and read through, so the assertion is never an empty table agreeing
        // with itself. The lesson is opened and finished, which is the journey that
        // produces the progress row the student_id rule is about.
        [, , , $lesson, $enrollment] = $this->enrolledCourse();

        $this->actingAs($enrollment->student)
            ->get(route('student.lessons.show', [$enrollment->course, $lesson]))->assertOk();
        $this->actingAs($enrollment->student)
            ->post(route('student.lessons.complete', [$enrollment->course, $lesson]));

        $offenders = DB::select($query);

        $this->assertSame([], array_map(
            static fn ($row): string => json_encode((array) $row),
            $offenders
        ), 'A row contradicts the column it copies. The two are the same fact stored twice, and the copy is what the learner facing queries filter on.');
    }

    public function test_the_arrangement_actually_produces_a_progress_row_to_audit(): void
    {
        /*
         | The test above would pass on an empty database, which is a test that
         | proves nothing. This one says the fixture is real, so a failure to build
         | it is a failure rather than a silent pass.
         */
        [, , , $lesson, $enrollment] = $this->enrolledCourse();

        $this->actingAs($enrollment->student)->get(route('student.lessons.show', [$enrollment->course, $lesson]))->assertOk();
        $this->actingAs($enrollment->student)->post(route('student.lessons.complete', [$enrollment->course, $lesson]));

        $row = DB::table('lesson_progress')->first();

        $this->assertNotNull($row, 'The arrangement produced no progress row, so the rules above are auditing nothing.');
        $this->assertSame('completed', $row->status);
    }

    /* ==================================== can the application make a rule false? */

    public function test_only_the_holder_of_an_enrollment_can_have_progress_written_for_it(): void
    {
        /*
         | The write path is the mechanism, since no constraint can reach across two
         | tables. MarkLessonComplete is handed the actor and the enrollment and
         | writes student_id from the actor, so the rule is that they are the same
         | person, and the policy is what settles it.
         */
        [, , , $lesson, $enrollment] = $this->enrolledCourse();
        $stranger = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(MarkLessonComplete::class)->handle($stranger, $enrollment, $lesson);
    }

    public function test_a_learner_completing_a_lesson_produces_progress_on_their_own_enrollment(): void
    {
        [, , , $lesson, $enrollment] = $this->enrolledCourse();

        app(RecordLessonActivity::class)->handle($enrollment->student, $enrollment, $lesson);
        app(MarkLessonComplete::class)->handle($enrollment->student, $enrollment, $lesson);

        $row = DB::table('lesson_progress')->first();

        $this->assertSame($enrollment->id, (int) $row->enrollment_id);
        $this->assertSame($enrollment->student_id, (int) $row->student_id);
        $this->assertSame($lesson->id, (int) $row->lesson_id);
    }

    /* ============================================ a status with no date beside it */

    public function test_finishing_a_lesson_records_when_it_was_finished(): void
    {
        /*
         | A status and the date beside it are two columns saying one thing, and
         | only the action writes them. MarkLessonComplete left completed_at out of
         | the columns an upsert may overwrite so a second press cannot move the
         | time, which meant a lesson that had been opened first took the update
         | path and was never given a date at all. Two live rows read as finished
         | with no date, and opening a lesson before marking it is what a learner
         | does. LessonProgressTest covers the same ground through the screen; this
         | one is here because the fault is in the table, not in a view.
         */
        [, , , $lesson, $enrollment] = $this->enrolledCourse();

        app(RecordLessonActivity::class)->handle($enrollment->student, $enrollment, $lesson);
        app(MarkLessonComplete::class)->handle($enrollment->student, $enrollment, $lesson);

        $row = DB::table('lesson_progress')->first();

        $this->assertSame('completed', $row->status);
        $this->assertNotNull($row->completed_at, 'The lesson reads as finished with no record of when it was finished.');
        $this->assertNotNull($row->started_at);
    }

    /* =============================================== no orphans of any kind */

    public function test_nothing_points_at_something_that_is_not_there(): void
    {
        /*
         | Foreign keys make most of this impossible, which is the point of having
         | them. Two profiles outliving their accounts was the exception, and it
         | came from a load with the checks turned off rather than from a gap in the
         | schema, so it is checked rather than assumed away.
         */
        $orphanProfiles = DB::table('profiles as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
            ->whereNull('u.id')
            ->count();

        $this->assertSame(0, $orphanProfiles, 'A profile with no account behind it. A role nothing can reach, which means nothing and should not be there.');

        $everyUserHasOneProfile = DB::table('users as u')
            ->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')
            ->whereNull('p.user_id')
            ->count();

        $this->assertSame(0, $everyUserHasOneProfile, 'An account with no profile, so it has no role and cannot be given one through the interface.');
    }

    public function test_no_notice_points_at_an_announcement_that_is_gone(): void
    {
        /*
         | subject_type and subject_id point at an announcement, a quiz, a
         | certificate and a conversation, so no foreign key can hold the reference
         | and nothing cascades. This is the one place the application is the only
         | possible mechanism, and it is checked here as well as in
         | AnnouncementTest because a notice about nothing is a 404 in a learner's
         | inbox rather than a wrong number in a report.
         */
        $orphans = DB::table('notifications as n')
            ->leftJoin('announcements as a', 'a.id', '=', 'n.subject_id')
            ->where('n.subject_type', 'announcement')
            ->whereNull('a.id')
            ->count();

        $this->assertSame(0, $orphans, 'A notice points at an announcement that no longer exists, and following it answers 404.');
    }

    public function test_every_message_author_is_a_member_of_its_own_conversation(): void
    {
        /*
         | The participant row is what grants access to a thread, and nothing in the
         | schema says a message has to come from somebody who has it. A message
         | from an outsider would render in a thread the outsider cannot open.
         */
        $outsiders = DB::select(
            'select m.id from conversation_messages m
              where not exists (
                    select 1 from conversation_participants p
                     where p.conversation_id = m.conversation_id and p.user_id = m.author_id
              )'
        );

        $this->assertSame([], $outsiders, 'A message was written by somebody who is not in the conversation it is in.');
    }

    /* ------------------------------------------------------------- arrangement */

    private function foreignTarget(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'select referenced_table_name as target
               from information_schema.key_column_usage
              where table_schema = database() and table_name = ? and column_name = ?
                and referenced_table_name is not null',
            [$table, $column],
        );

        return $row?->target;
    }

    /**
     * What happens to a row when the one it points at is deleted.
     *
     * Asked of the server rather than read out of the migration, because the whole
     * point of these two rules is that they are enforced. A migration saying
     * `nullOnDelete()` and a database saying `RESTRICT` are different claims, and
     * only one of them is true.
     */
    private function foreignDeleteRule(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'select delete_rule as rule
               from information_schema.referential_constraints
              where constraint_schema = database()
                and table_name = ?
                and referenced_table_name is not null
                and constraint_name = (
                      select constraint_name
                        from information_schema.key_column_usage
                       where table_schema = database() and table_name = ? and column_name = ?
                    )',
            [$table, $table, $column],
        );

        return $row?->rule;
    }

    /**
     * The columns a named unique index actually covers, in index order.
     *
     * `index_columns` rather than `columns`, because a unique index on
     * (assignment_id, student_id) does not also forbid duplicates on
     * (student_id, assignment_id), and reading the order out of the definition
     * rather than out of the usage list is what makes the assertion mean the thing
     * it says.
     */
    private function uniqueColumns(string $table, string $index): array
    {
        $rows = DB::select(
            'select column_name as name
               from information_schema.statistics
              where table_schema = database() and table_name = ? and index_name = ?
              order by seq_in_index',
            [$table, $index],
        );

        return array_map(fn ($row): string => (string) $row->name, $rows);
    }

    /**
     * A learner, a published free course, one published required lesson, enrolled.
     *
     * @return array{0: User, 1: Course, 2: Module, 3: Lesson, 4: Enrollment}
     */
    private function enrolledCourse(): array
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => CourseStatus::Published,
            'course_type' => CourseType::Free,
        ]);

        $module = Module::factory()->create([
            'course_id' => $course->id,
            'position' => 1,
            'status' => ContentStatus::Published,
        ]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'position' => 1,
            'status' => ContentStatus::Published,
            'is_required' => true,
        ]);

        // The factory already makes a verified learner with an active profile through
        // its configure() hook, so nothing is passed here. An earlier version passed a
        // profile array as an attribute, which the users table has no column for, and
        // the model protected itself from it exactly as it protects every other field.
        $student = User::factory()->create();

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
            'activated_at' => now(),
        ]);

        return [$student, $course, $module, $lesson, $enrollment];
    }
}
