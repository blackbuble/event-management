<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_liveness_endpoint_is_up(): void
    {
        $this->get(route('health.live'))
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_readiness_endpoint_reports_dependencies(): void
    {
        $this->get(route('health.ready'))
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'checks' => ['database' => true, 'cache' => true],
            ]);
    }
}
