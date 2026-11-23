<?php

namespace App\Services\Generations;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\PixelforgeGeneration;
use App\Services\Pixelforge\WebhookDeferred;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WebhookProcessor
{
    public function __construct(private readonly GenerationSynchronizer $synchronizer) {}

    public function process(WebhookEvent $event): void
    {
        if (in_array($event->status, [WebhookEvent::STATUS_PROCESSED, WebhookEvent::STATUS_IGNORED], true)) {
            return;
        }

        if ($event->type === 'ping') {
            $event->update([
                'status' => WebhookEvent::STATUS_IGNORED,
                'processed_at' => now(),
            ]);

            return;
        }

        if (! str_starts_with($event->type, 'generation.')) {
            $event->update([
                'status' => WebhookEvent::STATUS_IGNORED,
                'last_error' => 'Unsupported event type.',
                'processed_at' => now(),
            ]);

            return;
        }

        $object = $event->payload['data']['object'] ?? null;

        if (! is_array($object)) {
            $this->fail($event, 'Webhook event has no generation object.');

            return;
        }

        try {
            $provider = PixelforgeGeneration::fromArray($object);
        } catch (InvalidArgumentException $exception) {
            $this->fail($event, 'Invalid generation payload: '.$exception->getMessage());

            return;
        }

        $generation = $this->resolveGeneration($provider);

        if ($generation === null) {
            throw new WebhookDeferred('Generation not linked yet; awaiting the create response.');
        }

        $event->generation_id = $generation->id;
        $event->save();

        try {
            $this->synchronizer->apply($generation, $provider, 'webhook');
        } catch (\Throwable $exception) {
            $this->fail($event, $exception->getMessage());

            return;
        }

        $event->update([
            'status' => WebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
            'last_error' => null,
        ]);
    }

    private function resolveGeneration(PixelforgeGeneration $provider): ?Generation
    {
        $generation = Generation::query()
            ->where('provider_generation_id', $provider->id)
            ->first();

        if ($generation !== null) {
            return $generation;
        }

        $localId = $provider->metadataValue('vellum_generation_id');

        if ($localId === null) {
            return null;
        }

        return Generation::query()->where('public_id', $localId)->first();
    }

    private function fail(WebhookEvent $event, string $message): void
    {
        Log::warning('Webhook event could not be processed.', [
            'event' => $event->provider_event_id,
            'error' => $message,
        ]);

        $event->update([
            'status' => WebhookEvent::STATUS_FAILED,
            'last_error' => $message,
            'processed_at' => now(),
        ]);
    }
}
