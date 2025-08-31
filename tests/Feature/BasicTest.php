<?php

namespace Tests\Feature;

use Tests\TestCase;

class BasicTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Test that the application can be configured.
     */
    public function test_application_configuration(): void
    {
        $this->assertEquals('testing', config('app.env'));
        $this->assertNotEmpty(config('app.key'));
    }

    /**
     * Test that database connection works.
     */
    public function test_database_connection(): void
    {
        $this->assertDatabaseCount('migrations', 0, 'mysql');
    }
}