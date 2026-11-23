<?php

namespace Tests\Support;

use Database\Factories\GenerationFactory;
use Illuminate\Testing\TestResponse;

trait PixelforgeFixtures
{
    /**
     * @return array<string, mixed>
     */
    protected function pricingPayload(): array
    {
        $pricing = GenerationFactory::pricingSnapshot();

        return [
            'rates' => $pricing['rates'],
            'tokens' => $pricing['tokens'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function rates(): array
    {
        return [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function usage(): array
    {
        return [
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
            'total_tokens' => 16095,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function generationPayload(array $overrides = []): array
    {
        return array_replace([
            'id' => 'gen_test_1234567890abcdef',
            'object' => 'generation',
            'status' => 'queued',
            'prompt' => 'A quiet observatory above a sea of cloud, morning light, fine grain.',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'metadata' => ['vellum_generation_id' => '01TEST0000000000000000000'],
            'image_url' => null,
            'image_url_expires_at' => null,
            'usage' => null,
            'rates' => null,
            'error' => null,
            'created_at' => now()->toIso8601String(),
            'started_at' => null,
            'completed_at' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function signedWebhookHeaders(array $payload, string $eventId, string $type, ?int $timestamp = null): array
    {
        $timestamp ??= time();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) config('pixelforge.webhook_secret'));

        return [
            'Pixelforge-Event-Id' => $eventId,
            'Pixelforge-Event-Type' => $type,
            'Pixelforge-Delivery-Attempt' => '1',
            'Pixelforge-Signature' => "t={$timestamp},v1={$signature}",
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postSignedWebhook(array $payload, string $eventId, string $type, ?int $timestamp = null): TestResponse
    {
        $timestamp ??= time();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) config('pixelforge.webhook_secret'));

        return $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_EVENT_ID' => $eventId,
            'HTTP_PIXELFORGE_EVENT_TYPE' => $type,
            'HTTP_PIXELFORGE_DELIVERY_ATTEMPT' => '1',
            'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $body);
    }
}
