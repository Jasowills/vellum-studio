<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class PixelforgeClient
{
    /**
     * @param  array<int, string>  $referenceImages
     * @param  array<string, mixed>  $metadata
     */
    public function createGeneration(
        string $prompt,
        string $size,
        string $quality,
        array $referenceImages,
        array $metadata,
        string $idempotencyKey,
    ): PixelforgeGeneration {
        $response = $this->send(fn (PendingRequest $http) => $http
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/generations', [
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => array_values($referenceImages),
                'metadata' => $metadata,
            ]));

        return $this->generationFrom($response);
    }

    public function getGeneration(string $id): PixelforgeGeneration
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/generations/'.rawurlencode($id)));

        return $this->generationFrom($response);
    }

    /**
     * @return array{data: array<int, PixelforgeGeneration>, has_more: bool, next_cursor: ?string}
     */
    public function listGenerations(int $limit = 20, ?string $status = null, ?string $cursor = null): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/generations', array_filter([
            'limit' => $limit,
            'status' => $status,
            'cursor' => $cursor,
        ], fn ($value) => $value !== null)));

        $body = $response->json();

        if (! is_array($body) || ! is_array($body['data'] ?? null)) {
            throw PixelforgeException::invalidResponse('Pixelforge returned an invalid generation list.');
        }

        return [
            'data' => array_map(fn (array $item) => PixelforgeGeneration::fromArray($item), $body['data']),
            'has_more' => (bool) ($body['has_more'] ?? false),
            'next_cursor' => is_string($body['next_cursor'] ?? null) ? $body['next_cursor'] : null,
        ];
    }

    public function pricing(): PricingTable
    {
        $response = $this->send(
            fn (PendingRequest $http) => $http->get('/pricing'),
            authenticated: false,
        );

        $body = $response->json();

        if (! is_array($body)) {
            throw PixelforgeException::invalidResponse('Pixelforge returned an invalid pricing document.');
        }

        return PricingTable::fromPayload($body);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, total: array<string, mixed>}
     */
    public function usage(string $startDate, string $endDate): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));

        $body = $response->json();

        if (! is_array($body) || ! is_array($body['data'] ?? null) || ! is_array($body['total'] ?? null)) {
            throw PixelforgeException::invalidResponse('Pixelforge returned an invalid usage report.');
        }

        return $body;
    }

    private function generationFrom(Response $response): PixelforgeGeneration
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw PixelforgeException::invalidResponse('Pixelforge returned an invalid generation.');
        }

        try {
            return PixelforgeGeneration::fromArray($body);
        } catch (Throwable) {
            throw PixelforgeException::invalidResponse('Pixelforge returned an invalid generation.');
        }
    }

    private function send(callable $request, bool $authenticated = true): Response
    {
        if ($authenticated && blank(config('pixelforge.api_key'))) {
            throw PixelforgeException::configuration('PIXELFORGE_API_KEY is not configured.');
        }

        try {
            $response = $request($this->http($authenticated));
        } catch (ConnectionException $exception) {
            throw PixelforgeException::network($exception);
        }

        if (! $response->successful()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response;
    }

    private function http(bool $authenticated): PendingRequest
    {
        $http = Http::baseUrl(rtrim((string) config('pixelforge.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('pixelforge.timeout', 20))
            ->connectTimeout((int) config('pixelforge.connect_timeout', 5))
            ->withHeaders(['User-Agent' => 'Vellum/1.0 (image studio)'])
            ->withOptions(['http_errors' => false]);

        if ($authenticated) {
            $http = $http->withToken((string) config('pixelforge.api_key'));
        }

        return $http;
    }
}
