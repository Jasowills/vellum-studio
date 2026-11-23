<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    public function definition(): array
    {
        $pricing = self::pricingSnapshot();
        $usage = [
            'input_text_tokens' => 25,
            'input_image_tokens' => 0,
            'output_tokens' => 1100,
            'reasoning_tokens' => 900,
        ];
        $provider = Money::providerCostNanos($usage, $pricing['rates']);

        return [
            'user_id' => User::factory(),
            'public_id' => (string) Str::ulid(),
            'provider_generation_id' => 'gen_'.Str::upper(Str::random(24)),
            'idempotency_key' => 'vellum_u1_'.Str::uuid(),
            'request_hash' => hash('sha256', Str::random()),
            'status' => GenerationStatus::Pending,
            'prompt' => 'A quiet observatory above a sea of cloud, morning light, fine grain.',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'metadata' => [],
            'pricing_snapshot' => [
                'rates' => $pricing['rates'],
                'tokens' => $pricing['tokens'],
                'fetched_at' => now()->toIso8601String(),
                'rates_source' => 'pricing_endpoint',
            ],
            'estimate_snapshot' => [
                'provider_cost_nanos' => ['min' => $provider, 'max' => $provider],
                'customer_charge_nanos' => [
                    'min' => Money::customerChargeNanos($provider),
                    'max' => Money::customerChargeNanos($provider),
                ],
                'total_tokens' => ['min' => array_sum($usage), 'max' => array_sum($usage)],
            ],
            'estimated_provider_cost_min_nanos' => $provider,
            'estimated_provider_cost_max_nanos' => $provider,
            'estimated_customer_charge_min_nanos' => Money::customerChargeNanos($provider),
            'estimated_customer_charge_max_nanos' => Money::customerChargeNanos($provider),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pricingSnapshot(): array
    {
        return [
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => '0.15',
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => GenerationStatus::Queued,
            'provider_created_at' => now(),
            'next_reconcile_at' => now()->addMinute(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => GenerationStatus::Processing,
            'provider_created_at' => now()->subSeconds(20),
            'processing_at' => now()->subSeconds(15),
            'next_reconcile_at' => now()->addMinute(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(function (array $attributes) {
            $pricing = $attributes['pricing_snapshot'] ?? self::pricingSnapshot();
            $usage = [
                'input_text_tokens' => 25,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 900,
            ];
            $provider = Money::providerCostNanos($usage, $pricing['rates']);
            $charge = Money::customerChargeNanos($provider);

            return [
                'status' => GenerationStatus::Succeeded,
                'provider_created_at' => now()->subSeconds(30),
                'processing_at' => now()->subSeconds(25),
                'completed_at' => now()->subSeconds(5),
                'settled_at' => now()->subSeconds(5),
                'input_text_tokens' => $usage['input_text_tokens'],
                'input_image_tokens' => $usage['input_image_tokens'],
                'output_tokens' => $usage['output_tokens'],
                'reasoning_tokens' => $usage['reasoning_tokens'],
                'total_tokens' => array_sum($usage),
                'actual_provider_cost_nanos' => $provider,
                'actual_customer_charge_nanos' => $charge,
                'image_url' => 'https://pixelforge.promptedit.dev/images/gen_test.png?expires=1&signature=test',
                'image_url_expires_at' => now()->addDay(),
            ];
        });
    }

    public function failed(string $code = 'generation_failed'): static
    {
        return $this->state(fn () => [
            'status' => GenerationStatus::Failed,
            'provider_created_at' => now()->subSeconds(30),
            'processing_at' => now()->subSeconds(25),
            'completed_at' => now()->subSeconds(5),
            'failure_code' => $code,
            'failure_message' => 'The model could not produce this image.',
            'failure_stage' => 'generation',
            'provider_error' => ['code' => $code, 'message' => 'The model could not produce this image.'],
        ]);
    }
}
