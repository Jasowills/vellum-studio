<?php

namespace Database\Factories;

use App\Models\Generation;
use App\Models\GenerationCharge;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationCharge>
 */
class GenerationChargeFactory extends Factory
{
    public function definition(): array
    {
        $rates = [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ];
        $usage = [
            'input_text_tokens' => 25,
            'input_image_tokens' => 0,
            'output_tokens' => 1100,
            'reasoning_tokens' => 900,
        ];
        $provider = Money::providerCostNanos($usage, $rates);

        return [
            'generation_id' => Generation::factory(),
            'user_id' => User::factory(),
            'currency' => 'USD',
            'input_text_tokens' => $usage['input_text_tokens'],
            'input_image_tokens' => $usage['input_image_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'reasoning_tokens' => $usage['reasoning_tokens'],
            'total_tokens' => array_sum($usage),
            'provider_cost_nanos' => $provider,
            'customer_charge_nanos' => Money::customerChargeNanos($provider),
            'rates_snapshot' => $rates,
            'settled_at' => now(),
        ];
    }
}
