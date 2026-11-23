<?php

namespace App\Services\Pixelforge;

use InvalidArgumentException;

/**
 * Pixelforge's pricing document, validated into a predictable shape.
 *
 * Rates are kept as decimal strings. Token estimation parameters come from
 * GET /v1/pricing, never from a hard-coded table.
 */
final readonly class PricingTable
{
    /**
     * @param  array<string, mixed>  $rates
     * @param  array<string, mixed>  $outputTokens
     * @param  array<string, mixed>  $reasoning
     */
    public function __construct(
        public array $rates,
        public int $charactersPerTextToken,
        public string $textTokenVariance,
        public int $perReferenceImage,
        public array $outputTokens,
        public array $reasoning,
        public string $fetchedAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromPayload(array $payload, ?string $fetchedAt = null): self
    {
        $rates = $payload['rates'] ?? null;
        $tokens = $payload['tokens'] ?? null;

        if (! is_array($rates) || ! is_array($tokens)) {
            throw new InvalidArgumentException('Pixelforge pricing payload is missing rates or tokens.');
        }

        foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $key) {
            if (! isset($rates[$key]) || ! is_string($rates[$key]) || ! preg_match('/^\d+(\.\d+)?$/', $rates[$key])) {
                throw new InvalidArgumentException("Pixelforge pricing is missing a valid [{$key}] rate.");
            }
        }

        foreach (['characters_per_text_token', 'per_reference_image'] as $key) {
            if (! isset($tokens[$key]) || ! is_int($tokens[$key]) || $tokens[$key] < 1) {
                throw new InvalidArgumentException("Pixelforge pricing is missing a valid [{$key}] token parameter.");
            }
        }

        if (! isset($tokens['text_token_variance']) || ! is_numeric($tokens['text_token_variance'])) {
            throw new InvalidArgumentException('Pixelforge pricing is missing text_token_variance.');
        }

        foreach (['output', 'reasoning'] as $key) {
            if (! isset($tokens[$key]) || ! is_array($tokens[$key])) {
                throw new InvalidArgumentException("Pixelforge pricing is missing [{$key}] estimates.");
            }
        }

        foreach (['low', 'medium', 'high'] as $quality) {
            if (! isset($tokens['output'][$quality]) || ! is_array($tokens['output'][$quality])) {
                throw new InvalidArgumentException("Pixelforge pricing is missing output tokens for [{$quality}].");
            }

            foreach (array_keys($tokens['output'][$quality]) as $size) {
                if (! is_int($tokens['output'][$quality][$size]) || $tokens['output'][$quality][$size] < 1) {
                    throw new InvalidArgumentException("Pixelforge output tokens for [{$quality}/{$size}] are invalid.");
                }
            }

            $range = $tokens['reasoning'][$quality] ?? null;

            if (! is_array($range) || ! isset($range['min'], $range['max'])
                || ! is_int($range['min']) || ! is_int($range['max'])
                || $range['min'] < 0 || $range['max'] < $range['min']) {
                throw new InvalidArgumentException("Pixelforge reasoning range for [{$quality}] is invalid.");
            }
        }

        return new self(
            rates: $rates,
            charactersPerTextToken: $tokens['characters_per_text_token'],
            textTokenVariance: (string) $tokens['text_token_variance'],
            perReferenceImage: $tokens['per_reference_image'],
            outputTokens: $tokens['output'],
            reasoning: $tokens['reasoning'],
            fetchedAt: $fetchedAt ?? now()->toIso8601String(),
        );
    }

    public function rate(string $category): string
    {
        $rate = $this->rates[$category] ?? null;

        if (! is_string($rate)) {
            throw new InvalidArgumentException("Unknown rate [{$category}].");
        }

        return $rate;
    }

    public function outputTokensFor(string $quality, string $size): int
    {
        $tokens = $this->outputTokens[$quality][$size] ?? null;

        if (! is_int($tokens)) {
            throw new InvalidArgumentException("No output token estimate for [{$quality}] at [{$size}].");
        }

        return $tokens;
    }

    /**
     * @return array{min: int, max: int}
     */
    public function reasoningRangeFor(string $quality): array
    {
        $range = $this->reasoning[$quality] ?? null;

        if (! is_array($range) || ! isset($range['min'], $range['max'])
            || ! is_int($range['min']) || ! is_int($range['max'])) {
            throw new InvalidArgumentException("Unknown quality [{$quality}].");
        }

        return ['min' => $range['min'], 'max' => $range['max']];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rates' => $this->rates,
            'tokens' => [
                'characters_per_text_token' => $this->charactersPerTextToken,
                'text_token_variance' => $this->textTokenVariance,
                'per_reference_image' => $this->perReferenceImage,
                'output' => $this->outputTokens,
                'reasoning' => $this->reasoning,
            ],
            'fetched_at' => $this->fetchedAt,
        ];
    }
}
