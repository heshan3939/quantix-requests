<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user_on_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'employee@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'role', 'manager_id'],
            ]);
    }

    public function test_login_returns_422_with_consistent_shape_on_wrong_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'employee@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['email'],
            ]);
    }

    public function test_unauthenticated_request_returns_consistent_401_json(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
                'errors' => [
                    'auth' => ['You must be logged in to perform this action.'],
                ],
            ]);
    }

    public function test_not_found_returns_consistent_404_json(): void
    {
        $response = $this->getJson('/api/non-existent-route');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Resource not found.',
                'errors' => [
                    'resource' => ['The requested resource could not be found.'],
                ],
            ]);
    }
}
