<?php

namespace Tests\Feature\Qa;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HostileInput;
use Tests\TestCase;

/**
 * Charter C1: hostile input into every validated text field.
 *
 * The question is not "is the validation rule there". The rules are visible in
 * the Form Requests. The question is what the application does when a value the
 * rule did not anticipate arrives, and the three properties that must hold
 * whatever the value is:
 *
 * 1. The request is answered. No unhandled exception, no 500 from a value that
 *    is merely wrong rather than structurally broken.
 * 2. Nothing is written. A rejected value leaves no partial row, so a person
 *    who mistypes a title does not end up with an empty course beside it.
 * 3. The person is told something. A refused save comes back to the form with
 *    the reason, and with the text they typed still in it, because losing what
 *    someone typed is its own bug and a common one.
 *
 * Every case is a test whether or not it passes today, because the value of the
 * file is that a new field, a new rule, or a new endpoint is measured against
 * the same properties without anyone having to remember to add it.
 */
class HostileFormInputTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published course owned by an instructor, with a module and a lesson.
     *
     * @return array{0: User, 1: Course, 2: Module}
     */
    private function ownedCourse(): array
    {
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->for($instructor, 'instructor')->create();

        $module = Module::factory()->for($course, 'course')->create(['position' => 1]);

        return [$instructor, $course, $module];
    }

    /**
     * A payload that is valid apart from one field.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function coursePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'A perfectly ordinary title',
            'description' => 'An ordinary description.',
            'learning_objectives' => 'Learn one thing.',
            'category' => 'Computing',
            'level' => CourseLevel::Beginner->value,
            'course_type' => CourseType::Free->value,
            'price_minor' => 0,
        ], $overrides);
    }

    /**
     * A refusal is a redirect back to the form carrying the error, or a 422 for
     * a request that asked for JSON. Anything else is a bug, and a 500 in
     * particular means the value reached code that assumed it had already been
     * checked.
     *
     * The status alone does not say whether a save was accepted. A refused
     * browser form POST answers 302 exactly as a successful one does, so status
     * is checked for the crash case and the session is what decides accepted or
     * refused. Reading 302 as success is what made an earlier version of this
     * file report a defect that was not there.
     */
    private function assertAnsweredWithoutCrash(TestResponse $response, string $label): void
    {
        $status = $response->getStatusCode();

        $this->assertNotSame(500, $status, "{$label} produced a server error. A value the rules did not anticipate reached code that assumed it had been checked.");
        $this->assertNotSame(503, $status, "{$label} produced a service error.");
        $this->assertContains(
            $status,
            [200, 302, 303, 422],
            "{$label} answered with an unexpected {$status}."
        );
    }

    /**
     * Whether the request was refused, by asking the session rather than the
     * status code.
     */
    private function refused(TestResponse $response): bool
    {
        return $response->status() === 422
            || $response->baseRequest?->session()?->has('errors') === true;
    }

    /* ---------------------------------------------------- course creation */

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function textFieldValues(): array
    {
        $cases = [];

        foreach (HostileInput::classes() as $class => $value) {
            $cases["title: {$class}"] = ['title', $value];
            $cases["description: {$class}"] = ['description', $value];
            $cases["category: {$class}"] = ['category', $value];
            $cases["learning_objectives: {$class}"] = ['learning_objectives', $value];
        }

        return $cases;
    }

    #[DataProvider('textFieldValues')]
    public function test_course_creation_survives_any_text(string $field, string $value): void
    {
        [$instructor] = $this->ownedCourse();

        $before = Course::query()->count();

        $response = $this->actingAs($instructor)->post(
            '/instructor/courses',
            $this->coursePayload([$field => $value])
        );

        $this->assertAnsweredWithoutCrash($response, "creating a course with {$field} = ".json_encode($value, JSON_UNESCAPED_UNICODE));

        // Either it was refused and nothing was written, or it was accepted and
        // the value round trips. What must never happen is a refusal that still
        // wrote a row, which is how an empty course ends up in a catalog.
        $after = Course::query()->count();

        if ($this->refused($response)) {
            $this->assertSame(
                $before,
                $after,
                'The course was refused but a row was written anyway, so a partial record was left behind.'
            );

            return;
        }

        $this->assertSame($before + 1, $after);
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function boundaryLengths(): array
    {
        $cases = [];

        foreach (HostileInput::boundaryPairs() as $label => $pair) {
            $cases[$label] = $pair;
        }

        return $cases;
    }

    #[DataProvider('boundaryLengths')]
    public function test_course_title_boundary_is_decided_at_the_declared_length(int $atMax, int $overMax): void
    {
        [$instructor] = $this->ownedCourse();

        $rule = 160;

        $accepted = $this->actingAs($instructor)->post('/instructor/courses', $this->coursePayload([
            'title' => str_repeat('a', $atMax),
        ]));

        $this->assertAnsweredWithoutCrash($accepted, "a title of exactly {$atMax} characters");

        $this->assertNotSame(
            422,
            $accepted->getStatusCode(),
            "A title of {$atMax} characters was refused, but the rule allows {$rule}."
        );

        $before = Course::query()->count();

        $refused = $this->actingAs($instructor)->post('/instructor/courses', $this->coursePayload([
            'title' => str_repeat('a', $overMax),
        ]));

        $this->assertAnsweredWithoutCrash($refused, "a title of {$overMax} characters");

        if ($overMax > $rule) {
            $this->assertTrue(
                $this->refused($refused),
                "A title of {$overMax} characters was accepted, so the max:{$rule} rule is not being applied."
            );

            $this->assertSame(
                $before,
                Course::query()->count(),
                'An over-length title was refused but a course row was still written.'
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function typedValues(): array
    {
        return [
            'price as text' => ['price_minor', 'abc'],
            'price as a float' => ['price_minor', 1.5],
            'price as a boolean' => ['price_minor', true],
            'price as an array' => ['price_minor', [1, 2]],
            'price as a negative' => ['price_minor', -1],
            'price as zero' => ['price_minor', 0],
            'price beyond int' => ['price_minor', '99999999999999999999'],
            'price as NaN' => ['price_minor', 'NAN'],
            'price as Infinity' => ['price_minor', 'INF'],
            'price as null' => ['price_minor', null],
            'price as a nested array' => ['price_minor', [['deep']]],
            'price with a thousands separator' => ['price_minor', '1,000'],
            'price in hexadecimal' => ['price_minor', '0x1F'],
            'price in exponent form' => ['price_minor', '1e3'],
            'level as a number' => ['level', 1],
            'level as unknown text' => ['level', 'wizard'],
            'level as an array' => ['level', ['beginner']],
            'type as unknown text' => ['course_type', 'free-ish'],
            'title as an array' => ['title', ['a', 'b']],
            'title as a number' => ['title', 12345],
            'title as a boolean' => ['title', false],
            'title as null' => ['title', null],
            'description as an array' => ['description', ['x']],
        ];
    }

    #[DataProvider('typedValues')]
    public function test_course_creation_survives_a_wrong_type(string $field, mixed $value): void
    {
        [$instructor] = $this->ownedCourse();

        $before = Course::query()->count();

        $response = $this->actingAs($instructor)->post(
            '/instructor/courses',
            $this->coursePayload([$field => $value])
        );

        $this->assertAnsweredWithoutCrash($response, "creating a course with {$field} of a wrong type");

        if ($this->refused($response)) {
            $this->assertSame($before, Course::query()->count(), 'A wrong type was refused but a course was still written.');
        }
    }

    /* -------------------------------------------------------- other forms */

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function otherFormValues(): array
    {
        $cases = [];

        foreach (HostileInput::classes() as $class => $value) {
            $cases["module title: {$class}"] = ['module', 'title', $value];
            $cases["module description: {$class}"] = ['module', 'description', $value];
            $cases["profile name: {$class}"] = ['profile', 'name', $value];
            $cases["profile bio: {$class}"] = ['profile', 'bio', $value];
        }

        return $cases;
    }

    #[DataProvider('otherFormValues')]
    public function test_every_other_text_field_survives_hostile_input(string $form, string $field, string $value): void
    {
        [$instructor, $course, $module] = $this->ownedCourse();

        $response = match ($form) {
            'module' => $this->actingAs($instructor)->post(
                "/instructor/courses/{$course->id}/modules",
                ['title' => 'Module', 'description' => 'Body', $field => $value]
            ),
            'profile' => $this->actingAs($instructor)->patch('/account/profile', [
                'name' => 'A name',
                'bio' => 'A bio',
                $field => $value,
            ]),
        };

        $this->assertAnsweredWithoutCrash($response, "the {$form} form with {$field} = ".json_encode($value, JSON_UNESCAPED_UNICODE));

        // The seeded module is the one that already exists, so an accepted save
        // must leave exactly two. Asserting the count rather than the status is
        // what distinguishes an accepted save from a refused one, since both
        // answer with a redirect.
        if ($form === 'module' && ! $this->refused($response)) {
            $this->assertSame(
                2,
                $course->modules()->count(),
                'The module was accepted but the seeded module is gone, so the count did not increase.'
            );
        }
    }

    /* ------------------------------------------------ input is preserved */

    public function test_a_refused_course_reports_which_field_was_wrong(): void
    {
        [$instructor] = $this->ownedCourse();

        $response = $this->actingAs($instructor)->from('/instructor/courses/new')->post(
            '/instructor/courses',
            $this->coursePayload(['level' => 'wizard'])
        );

        $this->assertSame(302, $response->getStatusCode(), 'The save was not refused, so nothing was preserved and the test proves nothing.');

        $this->assertTrue(
            $this->refused($response),
            'The save was refused but the session carries no error, so the person is sent back to the form with nothing explained.'
        );

        $response->assertSessionHasErrors('level');
    }

    public function test_a_refused_course_keeps_what_the_person_typed(): void
    {
        [$instructor] = $this->ownedCourse();

        $title = 'A title I would not want to retype '.str_repeat('long ', 40);

        $response = $this->actingAs($instructor)->from('/instructor/courses/new')->post(
            '/instructor/courses',
            $this->coursePayload(['title' => $title, 'level' => 'wizard'])
        );

        $this->assertTrue($this->refused($response), 'The save was not refused, so nothing was preserved and the test proves nothing.');

        $followed = $this->actingAs($instructor)->get('/instructor/courses/new');

        $followed->assertOk();

        $body = (string) $followed->getContent();

        // The reason the old value has to come back is that a professor who has
        // written four hundred characters of learning objectives and then fixed
        // one dropdown should not lose them.
        //
        // Asserted on the value attribute rather than as a bare substring,
        // because the text also appears in the page's script and meta content.
        // A substring check would pass even if the form itself came back empty.
        $this->assertMatchesRegularExpression(
            '/value="'.preg_quote(substr($title, 0, 60), '/').'/',
            $body,
            'The refused form did not come back with the title that was typed, so the work was lost.'
        );

        $this->assertStringContainsString(
            'field-level-error',
            $body,
            'The refused form does not show where the problem is, so the person cannot tell what to fix.'
        );
    }

    public function test_a_valid_value_is_stored_exactly_as_typed(): void
    {
        [$instructor] = $this->ownedCourse();

        // The point of this one is that a title which is valid is not quietly
        // normalised into something else. A slug may change; the title is what
        // the instructor wrote and what the catalog shows.
        $title = 'Introduction to Programming — Module 1 (2026) · Level A';

        $this->actingAs($instructor)->post('/instructor/courses', $this->coursePayload([
            'title' => $title,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('courses', ['title' => $title]);
    }

    /* ----------------------------------------------------- fuzzed mixture */

    /**
     * @return array<string, array{0: int}>
     */
    public static function fuzzSeeds(): array
    {
        $cases = [];

        foreach ([1, 7, 42, 1337, 90210, 20260927] as $seed) {
            $cases["seed {$seed}"] = [$seed];
        }

        return $cases;
    }

    #[DataProvider('fuzzSeeds')]
    public function test_a_reproducible_mixture_of_everything_does_not_crash(int $seed): void
    {
        [$instructor] = $this->ownedCourse();

        $before = Course::query()->count();

        $response = $this->actingAs($instructor)->post('/instructor/courses', $this->coursePayload([
            'title' => HostileInput::mixed($seed, 200),
            'description' => HostileInput::mixed($seed + 1, 800),
            'category' => HostileInput::mixed($seed + 2, 120),
        ]));

        $this->assertAnsweredWithoutCrash($response, "seed {$seed} mixture");

        $this->assertLessThanOrEqual(
            $before + 1,
            Course::query()->count(),
            "Seed {$seed} wrote more than one course, so a single request produced duplicate records."
        );
    }

    /* ------------------------------------------------------ quiz questions */

    /**
     * A question payload, in the shape the form actually posts.
     *
     * The options arrive as an array of objects rather than as option_a through
     * option_d, which is the shape the request declares. Getting this wrong
     * produces a 422 that looks like a validation failure rather than a wrong
     * test, which is how a real question bug can be missed.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function questionPayload(array $overrides = []): array
    {
        return array_merge([
            'prompt' => 'A perfectly ordinary question',
            'options' => [
                ['option_text' => 'First', 'is_correct' => true],
                ['option_text' => 'Second', 'is_correct' => false],
            ],
        ], $overrides);
    }

    public function test_a_quiz_question_survives_hostile_text(): void
    {
        [$instructor, $course, $module] = $this->ownedCourse();

        $quiz = Quiz::factory()->for($course, 'course')->create(['module_id' => $module->id]);

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/quizzes/{$quiz->id}/questions", $this->questionPayload([
                'prompt' => HostileInput::mixed(5, 300),
                'options' => [
                    ['option_text' => HostileInput::mixed(6, 60), 'is_correct' => true],
                    ['option_text' => HostileInput::mixed(7, 60), 'is_correct' => false],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quiz_questions', 1);
        $this->assertDatabaseCount('quiz_options', 2);
    }

    /**
     * The option and prompt fields carry their own declared maxima, which are
     * declared in a different file from the course rules and so are easy to
     * leave out of a shared check.
     *
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function questionFieldLengths(): array
    {
        return [
            'prompt at 5000' => ['prompt', 5000, 5001],
            'option text at 500' => ['option_text', 500, 501],
        ];
    }

    #[DataProvider('questionFieldLengths')]
    public function test_a_quiz_question_boundary_is_enforced(string $field, int $allowed, int $over): void
    {
        [$instructor, $course, $module] = $this->ownedCourse();

        $quiz = Quiz::factory()->for($course, 'course')->create(['module_id' => $module->id]);

        $atLimit = $field === 'prompt'
            ? $this->questionPayload(['prompt' => str_repeat('p', $allowed)])
            : $this->questionPayload([
                'options' => [
                    ['option_text' => str_repeat('o', $allowed), 'is_correct' => true],
                    ['option_text' => 'Second', 'is_correct' => false],
                ],
            ]);

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/quizzes/{$quiz->id}/questions", $atLimit)
            ->assertSessionHasNoErrors();

        $overLimit = $field === 'prompt'
            ? $this->questionPayload(['prompt' => str_repeat('p', $over)])
            : $this->questionPayload([
                'options' => [
                    ['option_text' => str_repeat('o', $over), 'is_correct' => true],
                    ['option_text' => 'Second', 'is_correct' => false],
                ],
            ]);

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/quizzes/{$quiz->id}/questions", $overLimit)
            ->assertSessionHasErrors();
    }

    /**
     * Markers that claim to be true without being an explicit mark.
     *
     * Each of these is falsy or truthy under a loose cast in a way that changes
     * the answer key. The stored value is asserted, not just the status, because
     * the dangerous outcome is a question that saved successfully with two
     * correct options: it looks right and grades every response as correct.
     *
     * @return array<string, array{0: array<int, mixed>, 1: int}>
     */
    public static function answerKeyMarkers(): array
    {
        return [
            // PHP casts these to true, so a cast-based check marks both options.
            'two truthy strings' => [
                [
                    ['option_text' => 'First', 'is_correct' => 'yes'],
                    ['option_text' => 'Second', 'is_correct' => 'on'],
                ],
                2,
            ],
            'truthy string and integer' => [
                [
                    ['option_text' => 'First', 'is_correct' => 'yes'],
                    ['option_text' => 'Second', 'is_correct' => 1],
                ],
                2,
            ],
            'three truthy values' => [
                [
                    ['option_text' => 'First', 'is_correct' => '1'],
                    ['option_text' => 'Second', 'is_correct' => 'true'],
                    ['option_text' => 'Third', 'is_correct' => 1],
                ],
                3,
            ],
        ];
    }

    /**
     * A marker that reads as true must still leave exactly one correct option.
     *
     * @param  array<int, array<string, mixed>>  $options
     */
    #[DataProvider('answerKeyMarkers')]
    public function test_a_truthy_marker_cannot_produce_two_correct_options(array $options, int $castCount): void
    {
        [$instructor, $course, $module] = $this->ownedCourse();

        $quiz = Quiz::factory()->for($course, 'course')->create(['module_id' => $module->id]);

        $response = $this->actingAs($instructor)->post(
            "/instructor/courses/{$course->id}/quizzes/{$quiz->id}/questions",
            $this->questionPayload(['options' => $options])
        );

        // A loose cast would treat this as {$castCount} correct options, which
        // means any response scores as right. The question must be refused
        // instead, so the count in the case name is the bug being guarded.
        $this->assertTrue(
            $this->refused($response),
            "A question was written with {$castCount} truthy markers, so every answer would score as correct."
        );

        $this->assertDatabaseCount('quiz_questions', 0);
    }

    /**
     * A question written through a real form still stores exactly one answer.
     *
     * The fix for the case above narrows what counts as a mark, so this proves
     * the ordinary path was not narrowed too far. A checkbox posts '1' for the
     * marked box and nothing at all for the others.
     */
    public function test_a_real_checkbox_post_stores_exactly_one_correct_option(): void
    {
        [$instructor, $course, $module] = $this->ownedCourse();

        $quiz = Quiz::factory()->for($course, 'course')->create(['module_id' => $module->id]);

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/quizzes/{$quiz->id}/questions", $this->questionPayload([
                'options' => [
                    ['option_text' => 'Data link', 'is_correct' => '0'],
                    ['option_text' => 'Network', 'is_correct' => '1'],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $correct = DB::table('quiz_options')
            ->whereIn('question_id', DB::table('quiz_questions')->where('quiz_id', $quiz->id)->pluck('id'))
            ->where('is_correct', true)
            ->count();

        $this->assertSame(1, $correct, 'A form post of the ordinary shape did not store exactly one correct option.');
    }

    public function test_a_question_needs_at_least_two_options(): void
    {
        [$instructor, $course, $module] = $this->ownedCourse();

        $quiz = Quiz::factory()->for($course, 'course')->create(['module_id' => $module->id]);

        $before = DB::table('quiz_questions')->count();

        // One option is not a question, it is a statement, and a grader has
        // nothing to compare against.
        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/quizzes/{$quiz->id}/questions", $this->questionPayload([
                'options' => [
                    ['option_text' => 'The only option', 'is_correct' => true],
                ],
            ]))
            ->assertSessionHasErrors('options');

        $this->assertSame($before, DB::table('quiz_questions')->count());
    }

    public function test_a_question_cannot_carry_more_options_than_the_rule_allows(): void
    {
        [$instructor, $course, $module] = $this->ownedCourse();

        $quiz = Quiz::factory()->for($course, 'course')->create(['module_id' => $module->id]);

        $options = [];

        for ($i = 0; $i < 7; $i++) {
            $options[] = ['option_text' => "Option {$i}", 'is_correct' => $i === 0];
        }

        $this->actingAs($instructor)
            ->post("/instructor/courses/{$course->id}/quizzes/{$quiz->id}/questions", $this->questionPayload([
                'options' => $options,
            ]))
            ->assertSessionHasErrors('options');

        $this->assertSame(0, DB::table('quiz_questions')->count());
    }

    /* ---------------------------------------------- stored content is safe */

    /**
     * @return array<string, array{0: string}>
     */
    public static function storedPayloads(): array
    {
        return [
            'script element' => ['<script>window.__xss=1</script>'],
            'attribute break' => ['"><script>window.__xss=2</script>'],
            'image error' => ['<img src=x onerror="window.__xss=3">'],
            'svg onload' => ['<svg onload=window.__xss=4>'],
            'javascript url' => ['<a href="javascript:window.__xss=5">click</a>'],
            'iframe' => ['<iframe src="https://example.test"></iframe>'],
            'style expression' => ['<style>body{background:url(javascript:1)}</style>'],
            'html comment' => ['<!-- --><script>window.__xss=6</script>'],
        ];
    }

    #[DataProvider('storedPayloads')]
    public function test_stored_hostile_content_is_never_rendered_as_markup(string $payload): void
    {
        [$instructor] = $this->ownedCourse();

        $this->actingAs($instructor)->post('/instructor/courses', $this->coursePayload([
            'title' => 'A safe title',
            'description' => $payload,
            'level' => CourseLevel::Beginner->value,
            'course_type' => CourseType::Free->value,
        ]))->assertSessionHasNoErrors();

        $course = Course::query()->where('title', 'A safe title')->firstOrFail();

        // A draft is not in the catalog, and the catalog page 404s anything that
        // is not published. Without this the request below answers 404 and the
        // assertions pass on an empty page, which is how an encoding bug stays
        // hidden.
        $course->forceFill(['status' => CourseStatus::Published])->save();

        $catalog = (string) $this->get("/courses/{$course->slug}")->getContent();

        $this->assertStringContainsString(
            'A safe title',
            $catalog,
            'The course page did not render the course, so the encoding assertions below would pass on a 404 page.'
        );

        // The description reaches the page twice, in the meta description and in
        // the body. Both are produced by escaping, and a page can be correct in
        // one and wrong in the other, so each is checked where it appears rather
        // than the whole document at once.
        $this->assertEscaped($catalog, $payload);

        // A live handler can only exist inside a real tag, so the check is on
        // tag structure rather than on the handler name.
        //
        // An earlier version of this test searched for "onload=window.__xss" and
        // reported a defect. The page contained exactly that string, correctly
        // encoded as &lt;svg onload=window.__xss=4&gt; inside a meta tag, where
        // it is inert text. A substring search cannot tell an encoded attribute
        // from a live one, so it fails on a page doing exactly the right thing.
        // What has to be absent is a tag the browser would parse.
        $this->assertNoLiveTag($catalog);
    }

    /**
     * Assert the page contains no tag other than the ones the application ships.
     *
     * Parsed with the DOM rather than searched with a regular expression, so
     * that an encoded payload is correctly seen as text and a real element is
     * correctly seen as an element. This is the difference the substring search
     * could not make.
     */
    private function assertNoLiveTag(string $html): void
    {
        $allowed = [
            'html', 'head', 'body', 'meta', 'title', 'link', 'style', 'script',
            'div', 'span', 'p', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4',
            'button', 'form', 'input', 'label', 'select', 'option', 'header',
            'footer', 'main', 'nav', 'section', 'article', 'table', 'thead',
            'tbody', 'tr', 'th', 'td', 'svg', 'path', 'rect', 'circle', 'line',
            'polyline', 'polygon', 'g', 'defs', 'use', 'br', 'hr', 'img',
            'strong', 'em', 'small', 'time', 'dl', 'dt', 'dd', 'fieldset',
            'legend', 'details', 'summary', 'picture', 'source', 'progress',
        ];

        $previous = libxml_use_internal_errors(true);

        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($document->getElementsByTagName('*') as $element) {
            $name = strtolower($element->nodeName);

            // The application's own icon drawings are inline svg, and the brand
            // mark is a legitimate image. Neither is an injected element.
            if (in_array($name, $allowed, true)) {
                continue;
            }

            $this->fail("The stored value produced a live <{$name}> element on the page, so the markup was rendered rather than encoded.");
        }

        // The positive half of the claim, that the value is present rather than
        // discarded, is asserted by assertEscaped against the whole document.
        // It is not asserted against the DOM text content here, because two of
        // the payloads are a single tag with no text inside, so their text
        // content is empty even when the page is correct.
    }

    /**
     * Assert the page contains the value in encoded form and never in raw form.
     *
     * A raw check alone is not enough, because a template that simply dropped the
     * description would also pass it. Both directions are asserted so that
     * dropping content is not mistaken for encoding it.
     */
    private function assertEscaped(string $html, string $payload): void
    {
        $encoded = htmlspecialchars($payload, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (! str_contains($html, '&lt;') && ! str_contains($html, '&quot;')) {
            $this->assertStringContainsString(
                $encoded,
                $html,
                'The stored value was neither rendered nor encoded, so it was silently discarded.'
            );

            return;
        }

        $this->assertStringContainsString(
            $encoded,
            $html,
            'The stored value does not appear in encoded form, so it was rendered as markup.'
        );
    }
}
