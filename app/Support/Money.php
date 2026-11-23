<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Exact USD amounts.
 *
 * Money is stored and manipulated as integer nanodollars (1 USD = 1,000,000,000
 * nanodollars). Pixelforge reports USD with nine decimal places, so a
 * nanodollar is the smallest unit we ever need to persist. Conversion from
 * provider rates is done with arbitrary-precision decimals; PHP floats are
 * never used to calculate money.
 */
final class Money
{
    public const NANOS_PER_USD = 1_000_000_000;

    public const MARKUP_PERCENT = 25;

    public const TOKEN_CATEGORIES = [
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
    ];

    public static function providerCostNanos(array $usage, array $rates): int
    {
        $total = BigDecimal::zero();

        foreach (self::TOKEN_CATEGORIES as $category) {
            $tokens = $usage[$category] ?? 0;

            if (! is_int($tokens) && ! (is_string($tokens) && ctype_digit($tokens))) {
                throw new InvalidArgumentException("Token count for [{$category}] must be a non-negative integer.");
            }

            $tokens = (int) $tokens;

            if ($tokens < 0) {
                throw new InvalidArgumentException("Token count for [{$category}] must not be negative.");
            }

            $rate = $rates[$category] ?? null;

            if (! is_string($rate) || ! preg_match('/^\d+(\.\d+)?$/', $rate)) {
                throw new InvalidArgumentException("Rate for [{$category}] must be a decimal string.");
            }

            // rate is USD per 1,000,000 tokens. USD -> nanodollars is * 1e9,
            // so one token costs rate * 1e9 / 1e6 = rate * 1,000 nanodollars.
            $total = $total->plus(
                BigDecimal::of($rate)->multipliedBy($tokens)->multipliedBy(1_000),
            );
        }

        return $total->toScale(0, RoundingMode::HALF_UP)->toInt();
    }

    public static function customerChargeNanos(int $providerCostNanos): int
    {
        if ($providerCostNanos < 0) {
            throw new InvalidArgumentException('Provider cost cannot be negative.');
        }

        return BigDecimal::of($providerCostNanos)
            ->multipliedBy(100 + self::MARKUP_PERCENT)
            ->dividedBy(100, 0, RoundingMode::HALF_UP)
            ->toInt();
    }

    public static function usdString(int $nanos): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $absolute = abs($nanos);
        $whole = intdiv($absolute, self::NANOS_PER_USD);
        $fraction = str_pad((string) ($absolute % self::NANOS_PER_USD), 9, '0', STR_PAD_LEFT);

        return $sign.$whole.'.'.$fraction;
    }

    /**
     * Human-readable amount. Trailing zeroes are trimmed but at least
     * $minDecimals are always shown.
     */
    public static function formatUsd(int $nanos, int $minDecimals = 2): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $absolute = abs($nanos);
        $whole = intdiv($absolute, self::NANOS_PER_USD);
        $fraction = str_pad((string) ($absolute % self::NANOS_PER_USD), 9, '0', STR_PAD_LEFT);
        $trimmed = rtrim($fraction, '0');

        if (strlen($trimmed) < $minDecimals) {
            $trimmed = str_pad($trimmed, $minDecimals, '0');
        }

        return $sign.'$'.number_format($whole).($trimmed === '' ? '' : '.'.$trimmed);
    }

    /**
     * A full-precision ledger amount: exactly nine decimal places.
     */
    public static function formatExact(int $nanos): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $absolute = abs($nanos);
        $whole = intdiv($absolute, self::NANOS_PER_USD);
        $fraction = str_pad((string) ($absolute % self::NANOS_PER_USD), 9, '0', STR_PAD_LEFT);

        return $sign.'$'.number_format($whole).'.'.$fraction;
    }

    public static function fromUsdString(string $usd): int
    {
        if (! preg_match('/^\d+(\.\d{1,9})?$/', $usd)) {
            throw new InvalidArgumentException('USD amounts must have at most nine decimal places.');
        }

        return BigDecimal::of($usd)
            ->multipliedBy(self::NANOS_PER_USD)
            ->toScale(0, RoundingMode::UNNECESSARY)
            ->toInt();
    }
}
