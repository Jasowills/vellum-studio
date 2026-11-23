<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPixelforgeWebhookJob;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\InvalidWebhookSignature;
use App\Services\Pixelforge\WebhookSignature;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JsonException;

class WebhookController extends Controller
{
    public function __invoke(Request $request, WebhookSignature $signature): Response
    {
        $rawBody = $request->getContent();

        try {
            $timestamp = $signature->verify(
                $rawBody,
                $request->header('Pixelforge-Signature'),
                config('pixelforge.webhook_secret'),
            );
        } catch (InvalidWebhookSignature $exception) {
            Log::warning('Rejected a Pixelforge webhook signature.', [
                'reason' => $exception->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response('Invalid Pixelforge signature.', 400);
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response('Invalid JSON body.', 400);
        }

        if (! is_array($payload)) {
            return response('Invalid event body.', 400);
        }

        $eventId = $request->header('Pixelforge-Event-Id') ?? ($payload['id'] ?? null);
        $type = $request->header('Pixelforge-Event-Type') ?? ($payload['type'] ?? null);

        if (! is_string($eventId) || ! str_starts_with($eventId, 'evt_')
            || ! is_string($type) || $type === '') {
            return response('Missing event identity.', 400);
        }

        $deliveryAttempt = $request->header('Pixelforge-Delivery-Attempt');

        try {
            $event = WebhookEvent::create([
                'provider_event_id' => $eventId,
                'type' => $type,
                'status' => WebhookEvent::STATUS_PENDING,
                'payload' => $payload,
                'delivery_attempt' => is_numeric($deliveryAttempt) ? (int) $deliveryAttempt : null,
                'signature_timestamp' => $timestamp,
            ]);
        } catch (UniqueConstraintViolationException) {
            // At-least-once delivery is expected. A duplicate is a success.
            return response()->noContent();
        }

        ProcessPixelforgeWebhookJob::dispatch($event->id);

        return response()->noContent();
    }
}
