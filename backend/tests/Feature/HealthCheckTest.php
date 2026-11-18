<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_a_working_database_connection(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'services' => ['database' => 'connected'],
            ]);
    }

    public function test_health_endpoint_hides_database_errors_when_the_connection_fails(): void
    {
        DB::shouldReceive('connection')
            ->once()
            ->andReturn(new class
            {
                public function getPdo(): never
                {
                    throw new RuntimeException('Sensitive connection details');
                }
            });

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'degraded',
                'services' => ['database' => 'unavailable'],
            ])
            ->assertDontSee('Sensitive connection details');
    }
}
