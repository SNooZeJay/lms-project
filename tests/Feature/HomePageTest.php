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
            ->assertSee('BSIT Academic LMS')
            ->assertSee('A clear foundation for academic learning.')
            ->assertSee('Foundation preview')
            ->assertSee('Skip to main content')
            ->assertSee('data-theme-toggle', false);
    }
}
