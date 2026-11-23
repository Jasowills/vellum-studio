<?php

namespace App\Services\Pixelforge;

class WebhookSignature
{
    /**
     * Parse and verify a Pixelforge-Signature header.
     *
     * Header format: t=<unix timestamp>,v1=<hex hmac>
     * Signature: HMAC-SHA256(secret, "<timestamp>.<raw body>")
     *
     * @return int the verified timestamp
     */
    public function verify(string $rawBody, ?string $header, ?string $secret): int
    {
        if (blank($secret)) {
            throw new InvalidWebhookSignature('Webhook signing secret is not configured.');
        }

        if ($header === null || $header === '') {
            throw new InvalidWebhookSignature('Missing signature.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || $signatures === []) {
            throw new InvalidWebhookSignature('Malformed signature header.');
        }

        $timestamp = (int) $timestamp;
        $tolerance = (int) config('pixelforge.webhook_tolerance', 300);

        if (abs(time() - $timestamp) > $tolerance) {
            throw new InvalidWebhookSignature('Signature timestamp is outside the replay window.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return $timestamp;
            }
        }

        throw new InvalidWebhookSignature('Signature mismatch.');
    }
}
