<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_not_found_page_uses_safe_copy(): void
    {
        $response = $this->get('/foundation-page-does-not-exist');

        $response
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('The page may have moved or may not exist.');
    }

    public function test_forbidden_page_uses_safe_copy(): void
    {
        Route::middleware('web')->get('/__foundation-test/forbidden', static function () {
            abort(403);
        });

        $this->get('/__foundation-test/forbidden')
            ->assertForbidden()
            ->assertSee('Access denied')
            ->assertSee('You do not have permission to open this page.');
    }

    public function test_server_error_page_does_not_expose_exception_details(): void
    {
        config(['app.debug' => false]);

        Route::middleware('web')->get('/__foundation-test/failure', static function () {
            throw new RuntimeException('foundation-secret-marker');
        });

        $response = $this->get('/__foundation-test/failure');

        $response
            ->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertSee('The server could not complete this request.')
            ->assertDontSee('foundation-secret-marker', false);
    }
}
