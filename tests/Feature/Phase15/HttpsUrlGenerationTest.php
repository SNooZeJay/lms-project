<?php

namespace Tests\Feature\Phase15;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Behind a TLS-terminating proxy the request arrives as plain HTTP. If the
 * proxy does not send X-Forwarded-Proto, every generated URL carries http and a
 * browser refuses the stylesheet and the scripts as mixed content.
 */
class HttpsUrlGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        URL::forceScheme(null);
    }

    protected function tearDown(): void
    {
        URL::forceScheme(null);

        parent::tearDown();
    }

    public function test_an_https_app_url_generates_https_urls(): void
    {
        config(['app.url' => 'https://lms.example.com']);

        $this->rebootProvider();

        // Only the scheme is asserted. The host comes from the current request
        // or from the cached root, which is not what this test is about.
        $this->assertStringStartsWith(
            'https://',
            route('courses.index'),
            'An https APP_URL must produce https links, or the browser blocks the assets.'
        );
    }

    public function test_a_local_http_app_url_is_left_alone(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $this->rebootProvider();

        $this->assertStringStartsWith(
            'http://',
            route('courses.index'),
            'A local http APP_URL must not be upgraded, or development breaks.'
        );
    }

    public function test_the_scheme_survives_a_plain_http_request_behind_a_proxy(): void
    {
        config(['app.url' => 'https://lms.example.com']);

        $this->rebootProvider();

        // A reverse proxy forwards plain HTTP, so the request itself looks
        // insecure. The generated URLs must still be https.
        $this->get('http://lms.example.com/courses')->assertOk();

        $this->assertStringStartsWith('https://', route('courses.index'));
    }

    public function test_the_webhook_url_generated_for_the_checkout_is_https(): void
    {
        config(['app.url' => 'https://lms.example.com']);

        $this->rebootProvider();

        // The provider redirects the Student back here after paying. An http
        // URL would be blocked and the Student would land on nothing.
        $url = route('student.payments.return', 1);

        $this->assertStringStartsWith('https://', $url);
    }

    public function test_routes_still_resolve_after_forcing_the_scheme(): void
    {
        config(['app.url' => 'https://lms.example.com']);

        $this->rebootProvider();

        $router = Route::getRoutes()->getRoutes();

        foreach (['/', 'courses', 'login'] as $uri) {
            $matched = collect($router)->contains(
                fn ($route): bool => $route->uri() === $uri
            );

            $this->assertTrue($matched, "No route matched /{$uri}.");
        }
    }

    /**
     * Runs the provider boot logic again so the change to app.url takes effect
     * without a separate process.
     */
    private function rebootProvider(): void
    {
        $provider = new AppServiceProvider($this->app);
        $provider->boot();
    }
}
