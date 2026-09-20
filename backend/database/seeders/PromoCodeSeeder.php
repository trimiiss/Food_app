<?php

namespace Database\Seeders;

use App\Enums\PromoCodeType;
use App\Models\PromoCode;
use Illuminate\Database\Seeder;

/**
 * Demo promo codes, including deliberately unusable ones so the validation
 * messages can be seen without editing the database.
 */
class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            [
                'code' => 'WELCOME10',
                'description' => 'On everything, including dishes already on offer',
                'type' => PromoCodeType::Percent,
                'value' => 10,
                'min_subtotal' => 15,
            ],
            [
                'code' => 'FREESHIP',
                'description' => 'No delivery fee on your order',
                'type' => PromoCodeType::FreeDelivery,
                'value' => 0,
                'min_subtotal' => 20,
            ],
            [
                'code' => 'SAVE5',
                'description' => 'Straight off your order total',
                'type' => PromoCodeType::Fixed,
                'value' => 5,
                'min_subtotal' => 30,
            ],
            [
                // Not advertised on the Deals page — only usable if you were given it.
                'code' => 'STUDENT15',
                'description' => '15% student discount',
                'type' => PromoCodeType::Percent,
                'value' => 15,
                'min_subtotal' => 0,
                'is_public' => false,
            ],
            [
                // Expired on purpose: try it at checkout to see the error.
                'code' => 'SUMMER25',
                'description' => 'Last summer promotion',
                'type' => PromoCodeType::Percent,
                'value' => 25,
                'ends_at' => now()->subWeek(),
            ],
        ];

        foreach ($codes as $code) {
            PromoCode::updateOrCreate(['code' => $code['code']], $code);
        }
    }
}
