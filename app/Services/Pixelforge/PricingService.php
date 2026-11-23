<?php

namespace App\Services\Pixelforge;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PricingService
{
    private const CACHE_KEY = 'pixelforge:pricing:v1';

    public function __construct(private readonly PixelforgeClient $client) {}

    /**
     * Return fresh provider pricing. A recently cached document is reused; if
     * Pixelforge is briefly unavailable we fall back to the last known document
     * (marking it stale) rather than inventing prices.
     */
    public function current(): PricingTable
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $this->isFresh($cached)) {
            return $this->fromCache($cached);
        }

        try {
            return Cache::lock('pixelforge:pricing:refresh', 10)->block(5, function (): PricingTable {
                $cached = Cache::get(self::CACHE_KEY);

                if (is_array($cached) && $this->isFresh($cached)) {
                    return $this->fromCache($cached);
                }

                $pricing = $this->client->pricing();
                $payload = $pricing->toArray();
                $payload['fetched_at'] = now()->toIso8601String();

                Cache::put(self::CACHE_KEY, $payload, now()->addDay());

                return $this->fromCache($payload);
            });
        } catch (Throwable $exception) {
            if (is_array($cached)) {
                Log::warning('Pixelforge pricing refresh failed; using cached rates.', [
                    'error' => $exception::class,
                ]);

                return $this->fromCache($cached);
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->current()->toArray();
    }

    /**
     * @param  array<array-key, mixed>  $cached
     */
    private function isFresh(array $cached): bool
    {
        $fetchedAt = $cached['fetched_at'] ?? null;

        if (! is_string($fetchedAt)) {
            return false;
        }

        return now()->diffInSeconds($fetchedAt, true) < (int) config('pixelforge.pricing_ttl', 600);
    }

    /**
     * @param  array<array-key, mixed>  $cached
     */
    private function fromCache(array $cached): PricingTable
    {
        $rates = $cached['rates'] ?? null;
        $tokens = $cached['tokens'] ?? null;
        $fetchedAt = $cached['fetched_at'] ?? null;

        if (! is_array($rates) || ! is_array($tokens) || ($fetchedAt !== null && ! is_string($fetchedAt))) {
            throw PixelforgeException::invalidResponse('Cached pricing is invalid.');
        }

        return PricingTable::fromPayload(
            ['rates' => $rates, 'tokens' => $tokens],
            $fetchedAt,
        );
    }
}
