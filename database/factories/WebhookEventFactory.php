<?php

namespace Database\Factories;

use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    public function definition(): array
    {
        $eventId = 'evt_'.Str::upper(Str::random(24));

        return [
            'provider_event_id' => $eventId,
            'type' => 'generation.succeeded',
            'status' => 'pending',
            'generation_id' => null,
            'payload' => [
                'id' => $eventId,
                'object' => 'event',
                'type' => 'generation.succeeded',
                'created_at' => now()->toIso8601String(),
                'data' => ['object' => []],
            ],
            'delivery_attempt' => 1,
            'signature_timestamp' => now()->timestamp,
        ];
    }

    public function forGeneration(Generation $generation): static
    {
        return $this->state(fn () => ['generation_id' => $generation->id]);
    }
}
