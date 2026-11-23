<?php

namespace App\Services\Pixelforge;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Estimates a generation's token usage and cost range from the pricing
 * document. This is deliberately an honest range, never a guaranteed price.
 *
 * Method (from Pixelforge's documentation):
 *  - input text: ceil(Unicode characters / characters_per_text_token),
 *    with the documented variance applied in both directions;
 *  - reference images: exactly per_reference_image tokens each;
 *  - output tokens: exact for the requested quality and size;
 *  - reasoning: the documented min/max range for the quality.
 */
class EstimateService
{
    /**
     * @param  array<int, string>  $referenceImages
     * @return array<string, mixed>
     */
    public function estimate(PricingTable $pricing, string $prompt, string $size, string $quality, array $referenceImages): array
    {
        if (! in_array($size, config('pixelforge.sizes'), true)) {
            throw new InvalidArgumentException('Unsupported image size.');
        }

        if (! in_array($quality, config('pixelforge.qualities'), true)) {
            throw new InvalidArgumentException('Unsupported quality.');
        }

        $characters = mb_strlen($prompt);
        $baseTextTokens = (int) ceil($characters / $pricing->charactersPerTextToken);
        $variance = BigDecimal::of($pricing->textTokenVariance);

        $minTextTokens = max(0, BigDecimal::of($baseTextTokens)
            ->multipliedBy(BigDecimal::one()->minus($variance))
            ->toScale(0, RoundingMode::FLOOR)
            ->toInt());
        $maxTextTokens = BigDecimal::of($baseTextTokens)
            ->multipliedBy(BigDecimal::one()->plus($variance))
            ->toScale(0, RoundingMode::CEILING)
            ->toInt();

        $imageTokens = count($referenceImages) * $pricing->perReferenceImage;
        $outputTokens = $pricing->outputTokensFor($quality, $size);
        $reasoning = $pricing->reasoningRangeFor($quality);

        $usageMin = [
            'input_text_tokens' => $minTextTokens,
            'input_image_tokens' => $imageTokens,
            'output_tokens' => $outputTokens,
            'reasoning_tokens' => $reasoning['min'],
        ];
        $usageMax = [
            'input_text_tokens' => $maxTextTokens,
            'input_image_tokens' => $imageTokens,
            'output_tokens' => $outputTokens,
            'reasoning_tokens' => $reasoning['max'],
        ];

        $providerMin = Money::providerCostNanos($usageMin, $pricing->rates);
        $providerMax = Money::providerCostNanos($usageMax, $pricing->rates);
        $chargeMin = Money::customerChargeNanos($providerMin);
        $chargeMax = Money::customerChargeNanos($providerMax);

        return [
            'prompt_characters' => $characters,
            'text_tokens' => [
                'base' => $baseTextTokens,
                'min' => $minTextTokens,
                'max' => $maxTextTokens,
            ],
            'input_image_tokens' => $imageTokens,
            'output_tokens' => $outputTokens,
            'reasoning_tokens' => $reasoning,
            'total_tokens' => [
                'min' => array_sum($usageMin),
                'max' => array_sum($usageMax),
            ],
            'usage_min' => $usageMin,
            'usage_max' => $usageMax,
            'provider_cost_nanos' => ['min' => $providerMin, 'max' => $providerMax],
            'customer_charge_nanos' => ['min' => $chargeMin, 'max' => $chargeMax],
            'provider_cost' => [
                'min' => Money::formatUsd($providerMin),
                'max' => Money::formatUsd($providerMax),
            ],
            'customer_charge' => [
                'min' => Money::formatUsd($chargeMin),
                'max' => Money::formatUsd($chargeMax),
            ],
            'rates' => $pricing->rates,
            'pricing_fetched_at' => $pricing->fetchedAt,
            'markup_percent' => Money::MARKUP_PERCENT,
        ];
    }
}
