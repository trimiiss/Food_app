<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the "clone, migrate --seed, demo" experience promised in the README.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_app_is_immediately_demoable(): void
    {
        $this->seed(DatabaseSeeder::class);

        // The credentials documented in the README actually work.
        $this->postJson('/api/v1/login', ['email' => UserSeeder::ADMIN_EMAIL, 'password' => UserSeeder::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'admin');

        $this->postJson('/api/v1/login', ['email' => UserSeeder::CUSTOMER_EMAIL, 'password' => UserSeeder::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'customer');

        // Storefront has content; the one unavailable product is hidden.
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(9, 'data');
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('meta.total', 47);

        $this->assertDatabaseCount('orders', 4);
    }

    public function test_seeding_twice_does_not_duplicate_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('categories', 9);
        $this->assertDatabaseCount('products', 48);
        $this->assertDatabaseCount('orders', 4);
    }
}
