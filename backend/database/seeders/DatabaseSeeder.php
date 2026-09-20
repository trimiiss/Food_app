<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CatalogSeeder::class,
            // Before the demo orders: one of them is placed with a promo code.
            PromoCodeSeeder::class,
            DemoOrderSeeder::class,
        ]);
    }
}
