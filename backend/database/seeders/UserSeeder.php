<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts. Credentials are documented in the root README.
 */
class UserSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@leueats.test';

    public const CUSTOMER_EMAIL = 'customer@leueats.test';

    public const PASSWORD = 'password';

    public function run(): void
    {
        $this->upsertUser('Admin', self::ADMIN_EMAIL, UserRole::Admin);
        $this->upsertUser('Demo Customer', self::CUSTOMER_EMAIL, UserRole::Customer);
    }

    private function upsertUser(string $name, string $email, UserRole $role): void
    {
        // firstOrNew + explicit role: role is not mass assignable by design.
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = self::PASSWORD;
        $user->role = $role;
        $user->email_verified_at ??= now();
        $user->save();
    }
}
