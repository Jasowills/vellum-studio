<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_prices_the_documented_maximum_example_exactly(): void
    {
        $usage = [
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ];
        $rates = [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ];

        $providerCost = Money::providerCostNanos($usage, $rates);

        $this->assertSame(373_875_000, $providerCost);
        $this->assertSame('0.373875000', Money::usdString($providerCost));
        $this->assertSame(467_343_750, Money::customerChargeNanos($providerCost));
        $this->assertSame('$0.467343750', Money::formatExact(Money::customerChargeNanos($providerCost)));
    }

    public function test_it_adds_the_25_percent_markup_with_half_up_rounding(): void
    {
        $this->assertSame(1, Money::customerChargeNanos(1));
        $this->assertSame(3, Money::customerChargeNanos(2));
        $this->assertSame(4, Money::customerChargeNanos(3));
        $this->assertSame(1_250_000_000, Money::customerChargeNanos(1_000_000_000));
    }

    public function test_it_rounds_provider_cost_only_after_summing_all_token_categories(): void
    {
        $rates = [
            'input_text_tokens' => '0.0006',
            'input_image_tokens' => '0.0006',
            'output_tokens' => '0.0006',
            'reasoning_tokens' => '0.0006',
        ];

        // Each category contributes 0.6 nano; summing first gives 2.
        $this->assertSame(2, Money::providerCostNanos([
            'input_text_tokens' => 1,
            'input_image_tokens' => 1,
            'output_tokens' => 1,
            'reasoning_tokens' => 1,
        ], $rates));
    }

    public function test_it_parses_and_formats_nine_decimal_usd_amounts(): void
    {
        $this->assertSame(1, Money::fromUsdString('0.000000001'));
        $this->assertSame(1_234_567_890, Money::fromUsdString('1.234567890'));
        $this->assertSame('$1.234567890', Money::formatExact(1_234_567_890));
        $this->assertSame('$1.23456789', Money::formatUsd(1_234_567_890));
    }

    public function test_it_rejects_amounts_more_precise_than_a_nanodollar(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromUsdString('0.0000000001');
    }

    public function test_it_rejects_negative_token_counts_and_float_rates(): void
    {
        $rates = [
            'input_text_tokens' => 5.0,
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ];

        $this->expectException(InvalidArgumentException::class);

        Money::providerCostNanos(['input_text_tokens' => -1], $rates);
    }
}
