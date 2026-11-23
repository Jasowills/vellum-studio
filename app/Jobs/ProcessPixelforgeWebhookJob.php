<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Generations\WebhookProcessor;
use App\Services\Pixelforge\WebhookDeferred;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPixelforgeWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly int $eventId) {}

    public function handle(WebhookProcessor $processor): void
    {
        $event = WebhookEvent::query()->find($this->eventId);

        if ($event === null || in_array($event->status, [WebhookEvent::STATUS_PROCESSED, WebhookEvent::STATUS_IGNORED], true)) {
            return;
        }

        $event->increment('processing_attempts');
        $event->refresh();

        try {
            $processor->process($event);
        } catch (WebhookDeferred $exception) {
            $this->defer($event, $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Unexpected webhook processing failure.', [
                'event' => $event->provider_event_id,
                'error' => $exception::class,
            ]);

            $this->defer($event, 'Unexpected processing error.');
        }
    }

    private function defer(WebhookEvent $event, string $reason): void
    {
        $event->refresh();

        if ($event->processing_attempts >= 8) {
            $event->update([
                'status' => WebhookEvent::STATUS_FAILED,
                'last_error' => $reason,
            ]);

            Log::error('Webhook event exhausted retries.', [
                'event' => $event->provider_event_id,
                'reason' => $reason,
            ]);

            return;
        }

        $delay = min(120, 5 * $event->processing_attempts);
        $event->update(['last_error' => $reason]);

        self::dispatch($event->id)->delay(now()->addSeconds($delay));
    }
}
