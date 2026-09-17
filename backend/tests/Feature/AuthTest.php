<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_customer_can_register_and_receives_a_token(): void
    {
        $this->postJson('/api/v1/register', $this->registrationPayload())
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_registration_cannot_escalate_role_via_payload(): void
    {
        $this->postJson('/api/v1/register', $this->registrationPayload(['role' => 'admin']))
            ->assertCreated()
            ->assertJsonPath('data.user.role', 'customer');

        $this->assertSame(UserRole::Customer, User::firstWhere('email', 'jane@example.com')->role);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/register', $this->registrationPayload(['password_confirmation' => 'nope']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_admin_can_register_with_the_correct_code(): void
    {
        config(['auth.admin_registration_code' => 'top-secret']);

        $this->postJson('/api/v1/admin/register', $this->registrationPayload(['registration_code' => 'top-secret']))
            ->assertCreated()
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.user.is_admin', true);
    }

    public function test_admin_registration_rejects_a_wrong_code(): void
    {
        config(['auth.admin_registration_code' => 'top-secret']);

        $this->postJson('/api/v1/admin/register', $this->registrationPayload(['registration_code' => 'guess']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['registration_code']);

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_admin_registration_is_disabled_without_a_configured_code(): void
    {
        config(['auth.admin_registration_code' => null]);

        $this->postJson('/api/v1/admin/register', $this->registrationPayload(['registration_code' => '']))
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin registration is disabled.');
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->postJson('/api/v1/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/login', ['email' => 'jane@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_protected_route_returns_401_json_even_without_accept_header(): void
    {
        // Deliberately `get`, not `getJson`: proves ForceJsonResponse prevents the
        // default redirect to a non-existent `login` route (which would be a 500).
        $this->get('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('spa')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('spa')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Reset the resolved guard so the next request re-authenticates from scratch.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
