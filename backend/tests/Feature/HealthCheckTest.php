<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_health_check_returns_successful_response(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'API is running',
                     'data' => [
                         'application' => env('APP_NAME', 'AI Facebook Content Tool'),
                         'environment' => env('APP_ENV', 'local'),
                     ]
                 ]);
    }
}
