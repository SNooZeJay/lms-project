<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_renders_the_foundation_shell(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('IT Learning Hub')
            ->assertSee('A clear foundation for academic learning.')
            ->assertSee('Foundation preview')
            ->assertSee('Sign in')
            ->assertSee('Create student account')
            ->assertSee('Skip to main content')
            ->assertSee('data-theme-toggle', false);
    }
}
