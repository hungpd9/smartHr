<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_visit_dashboard(): void
    {
        $user = \App\Models\User::where('role', \App\Enums\UserRole::SUPER_ADMIN)->first();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
    }
}
