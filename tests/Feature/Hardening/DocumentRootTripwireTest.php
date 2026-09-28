<?php

namespace Tests\Feature\Hardening;

use App\Http\Middleware\RefuseWhenProjectIsWebReadable;
use Tests\TestCase;

/**
 * That the application refuses to answer when the server is rooted wrongly.
 *
 * When the document root is the project directory rather than public/, the web
 * server hands out .git, .env, storage/, and the source without the framework
 * ever running. No middleware can prevent that, because the request never
 * reaches the framework. The only control that works is refusing to answer at
 * all, so that the misconfiguration is reported by a visitor rather than
 * discovered later.
 *
 * Each case sets the document root to a real arrangement and asks the same
 * question, because the failure being guarded against is a wrong value in
 * server configuration and only the value matters.
 */
class DocumentRootTripwireTest extends TestCase
{
    /**
     * @var string|false
     */
    private mixed $originalDocumentRoot = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? false;
    }

    protected function tearDown(): void
    {
        if ($this->originalDocumentRoot === false) {
            unset($_SERVER['DOCUMENT_ROOT']);
        } else {
            $_SERVER['DOCUMENT_ROOT'] = $this->originalDocumentRoot;
        }

        parent::tearDown();
    }

    private function middleware(): RefuseWhenProjectIsWebReadable
    {
        return app(RefuseWhenProjectIsWebReadable::class);
    }

    public function test_a_correct_document_root_does_not_trip(): void
    {
        // The arrangement a Laravel deployment is supposed to have.
        $_SERVER['DOCUMENT_ROOT'] = public_path();

        $this->assertFalse(
            $this->middleware()->exposesProjectDirectory(),
            'A correct deployment was reported as exposing the project directory, which would take the site offline.'
        );

        $this->get('/')->assertOk();
    }

    public function test_a_document_root_above_the_project_trips(): void
    {
        // The mistake this exists for: rooted at the project directory, or at
        // anything above it. The whole project becomes readable.
        $_SERVER['DOCUMENT_ROOT'] = base_path();

        $this->assertTrue(
            $this->middleware()->exposesProjectDirectory(),
            'A server rooted at the project directory was not detected.'
        );

        $this->get('/')->assertStatus(500);
    }

    public function test_a_document_root_above_the_project_parent_trips(): void
    {
        // One level higher still, as when several projects share a htdocs.
        $_SERVER['DOCUMENT_ROOT'] = dirname(base_path());

        $this->assertTrue($this->middleware()->exposesProjectDirectory());
    }

    public function test_the_refusal_does_not_describe_the_server(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = base_path();

        $body = (string) $this->get('/')->getContent();

        // The page is read by whoever is looking at a broken deployment, which
        // may be a stranger on a public address. It may say the deployment is
        // wrong and say what to change, and it may not print the paths, the
        // configuration, or a file name. Naming the mistake is the whole point:
        // a silent refusal is indistinguishable from an outage.
        $this->assertStringContainsString('document root', $body);

        foreach ([base_path(), 'DOCUMENT_ROOT', 'APP_KEY', '.env', 'vendor/'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, "The refusal page leaked {$needle}.");
        }
    }

    public function test_an_unknown_document_root_is_left_alone(): void
    {
        // Not knowing must not mean refusing. A reverse proxy, a container, and
        // a command line process all report something other than a local path,
        // and breaking those would be worse than the mistake being guarded.
        unset($_SERVER['DOCUMENT_ROOT']);

        $this->assertFalse($this->middleware()->exposesProjectDirectory());

        $_SERVER['DOCUMENT_ROOT'] = '';

        $this->assertFalse($this->middleware()->exposesProjectDirectory());

        $this->get('/')->assertOk();
    }

    public function test_a_document_root_that_does_not_exist_is_left_alone(): void
    {
        // A path belonging to another machine, which is what a proxy or a
        // container reports. It cannot be resolved, so it is not evidence.
        $_SERVER['DOCUMENT_ROOT'] = '/var/www/app';

        $this->assertFalse($this->middleware()->exposesProjectDirectory());
        $this->get('/')->assertOk();
    }

    public function test_a_separator_difference_is_not_a_difference(): void
    {
        // Windows reports backslashes and PHP reports forward slashes. Treating
        // those as different directories would refuse a correct deployment,
        // which is the one outcome worse than the mistake.
        $_SERVER['DOCUMENT_ROOT'] = str_replace('/', '\\', public_path()).'\\';

        $this->assertFalse(
            $this->middleware()->exposesProjectDirectory(),
            'A trailing separator or a backslash was treated as a different document root.'
        );
    }

    public function test_a_differently_named_directory_is_not_the_same_one(): void
    {
        // "…/public-backup" contains "…/public" as a string but is not inside it.
        // A substring comparison alone would get this backwards.
        $_SERVER['DOCUMENT_ROOT'] = public_path().'-backup';

        $this->assertFalse(
            $this->middleware()->exposesProjectDirectory(),
            'A sibling directory whose name merely starts the same was treated as the document root.'
        );
    }
}
