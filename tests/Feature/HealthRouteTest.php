<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthRouteTest extends TestCase
{
    public function test_health_route_returns_a_successful_response(): void
    {
        $this->get('/up')
            ->assertOk();
    }
}
