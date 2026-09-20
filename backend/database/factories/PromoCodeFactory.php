<?php

namespace Database\Factories;

use App\Enums\PromoCodeType;
use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->bothify('SAVE##??')),
            'description' => fake()->sentence(4),
            'type' => PromoCodeType::Percent,
            'value' => 10,
            'min_subtotal' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'max_uses' => null,
            'uses_count' => 0,
            'is_active' => true,
            'is_public' => true,
        ];
    }

    public function percent(float $percent, float $minSubtotal = 0): static
    {
        return $this->state(['type' => PromoCodeType::Percent, 'value' => $percent, 'min_subtotal' => $minSubtotal]);
    }

    public function fixed(float $amount, float $minSubtotal = 0): static
    {
        return $this->state(['type' => PromoCodeType::Fixed, 'value' => $amount, 'min_subtotal' => $minSubtotal]);
    }

    public function freeDelivery(float $minSubtotal = 0): static
    {
        return $this->state(['type' => PromoCodeType::FreeDelivery, 'value' => 0, 'min_subtotal' => $minSubtotal]);
    }

    public function expired(): static
    {
        return $this->state(['ends_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function fullyRedeemed(): static
    {
        return $this->state(['max_uses' => 5, 'uses_count' => 5]);
    }
}
