<?php

namespace Tests\Unit;

use App\Services\Pixelforge\InvalidWebhookSignature;
use App\Services\Pixelforge\WebhookSignature;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    public function test_it_verifies_a_signed_raw_body(): void
    {
        $body = '{"id":"evt_test"}';
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) config('pixelforge.webhook_secret'));

        $verified = app(WebhookSignature::class)->verify(
            $body,
            "t={$timestamp},v1={$signature}",
            (string) config('pixelforge.webhook_secret'),
        );

        $this->assertSame($timestamp, $verified);
    }

    public function test_it_accepts_any_matching_signature_in_the_header(): void
    {
        $body = '{"id":"evt_test"}';
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) config('pixelforge.webhook_secret'));
        $header = "t={$timestamp},v1=deadbeef,v1={$signature}";

        $this->assertSame(
            $timestamp,
            app(WebhookSignature::class)->verify($body, $header, (string) config('pixelforge.webhook_secret')),
        );
    }

    public function test_it_rejects_a_tampered_body(): void
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.{"id":"evt_test"}', (string) config('pixelforge.webhook_secret'));

        $this->expectException(InvalidWebhookSignature::class);

        app(WebhookSignature::class)->verify(
            '{"id":"evt_tampered"}',
            "t={$timestamp},v1={$signature}",
            (string) config('pixelforge.webhook_secret'),
        );
    }

    public function test_it_rejects_signatures_outside_the_replay_window(): void
    {
        $body = '{"id":"evt_test"}';
        $timestamp = time() - 400;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) config('pixelforge.webhook_secret'));

        $this->expectException(InvalidWebhookSignature::class);
        $this->expectExceptionMessage('outside the replay window');

        app(WebhookSignature::class)->verify(
            $body,
            "t={$timestamp},v1={$signature}",
            (string) config('pixelforge.webhook_secret'),
        );
    }

    public function test_it_rejects_a_missing_signature_header(): void
    {
        $this->expectException(InvalidWebhookSignature::class);

        app(WebhookSignature::class)->verify('{}', null, (string) config('pixelforge.webhook_secret'));
    }
}
