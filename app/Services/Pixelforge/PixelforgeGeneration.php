<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A validated Pixelforge generation object. The same shape is returned by
 * create, retrieve and inside webhook events.
 */
final readonly class PixelforgeGeneration
{
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public string $prompt,
        public string $size,
        public string $quality,
        public array $referenceImages,
        public array $metadata,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageExpiresAt,
        public ?array $usage,
        public ?array $rates,
        public ?array $error,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (! is_string($id) || ! str_starts_with($id, 'gen_')) {
            throw new InvalidArgumentException('Pixelforge generation id is invalid.');
        }

        if (! is_string($status) || GenerationStatus::tryFrom($status) === null) {
            throw new InvalidArgumentException('Pixelforge generation status is invalid.');
        }

        $usage = $data['usage'] ?? null;
        $rates = $data['rates'] ?? null;

        if ($usage !== null && is_array($usage)) {
            $usage = self::normalizeUsage($usage);
        }

        if ($rates !== null && is_array($rates)) {
            foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $key) {
                if (! isset($rates[$key]) || ! is_string($rates[$key]) || ! preg_match('/^\d+(\.\d+)?$/', $rates[$key])) {
                    throw new InvalidArgumentException("Pixelforge generation rates are missing or invalid for [{$key}].");
                }
            }
        }

        return new self(
            id: $id,
            status: GenerationStatus::from($status),
            prompt: (string) ($data['prompt'] ?? ''),
            size: (string) ($data['size'] ?? ''),
            quality: (string) ($data['quality'] ?? ''),
            referenceImages: array_values(array_filter((array) ($data['reference_images'] ?? []), 'is_string')),
            metadata: (array) ($data['metadata'] ?? []),
            imageUrl: isset($data['image_url']) && is_string($data['image_url']) ? $data['image_url'] : null,
            imageExpiresAt: self::date($data['image_url_expires_at'] ?? null),
            usage: $usage,
            rates: $rates,
            error: isset($data['error']) && is_array($data['error']) ? $data['error'] : null,
            createdAt: self::date($data['created_at'] ?? null),
            startedAt: self::date($data['started_at'] ?? null),
            completedAt: self::date($data['completed_at'] ?? null),
        );
    }

    public function metadataValue(string $key): ?string
    {
        $value = $this->metadata[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function totalTokens(): ?int
    {
        if ($this->usage === null) {
            return null;
        }

        return (int) ($this->usage['total_tokens'] ?? array_sum($this->usage));
    }

    private static function normalizeUsage(array $usage): array
    {
        $normalized = [];

        foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $key) {
            $value = $usage[$key] ?? null;

            if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
                throw new InvalidArgumentException("Pixelforge usage is missing a valid [{$key}] count.");
            }

            $normalized[$key] = (int) $value;
        }

        $normalized['total_tokens'] = isset($usage['total_tokens']) && is_numeric($usage['total_tokens'])
            ? (int) $usage['total_tokens']
            : array_sum($normalized);

        return $normalized;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->utc();
    }
}
