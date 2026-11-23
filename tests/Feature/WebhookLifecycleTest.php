<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\GenerationCharge;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PixelforgeFixtures;
use Tests\TestCase;

class WebhookLifecycleTest extends TestCase
{
    use PixelforgeFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_a_verified_success_webhook_settles_once_and_ignores_replays_and_stale_events(): void
    {
        $generation = Generation::factory()->create([
            'status' => GenerationStatus::Processing,
            'provider_generation_id' => 'gen_test_1234567890abcdef',
        ]);

        $succeeded = $this->generationPayload([
            'id' => $generation->provider_generation_id,
            'status' => 'succeeded',
            'metadata' => ['vellum_generation_id' => $generation->public_id],
            'usage' => $this->usage(),
            'rates' => $this->rates(),
            'completed_at' => now()->toIso8601String(),
        ]);

        $event = [
            'id' => 'evt_succeed_000000000001',
            'object' => 'event',
            'type' => 'generation.succeeded',
            'created_at' => now()->toIso8601String(),
            'data' => ['object' => $succeeded],
        ];

        $this->postSignedWebhook($event, $event['id'], 'generation.succeeded')->assertNoContent();

        $generation->refresh();

        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(373_875_000, $generation->actual_provider_cost_nanos);
        $this->assertSame(467_343_750, $generation->actual_customer_charge_nanos);
        $this->assertSame(1, GenerationCharge::query()->count());
        $this->assertSame(WebhookEvent::STATUS_PROCESSED, WebhookEvent::query()->sole()->status);

        $chargeId = GenerationCharge::query()->sole()->id;

        $this->postSignedWebhook($event, $event['id'], 'generation.succeeded')->assertNoContent();
        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame($chargeId, GenerationCharge::query()->sole()->id);

        $stale = [
            'id' => 'evt_processing_00000000001',
            'object' => 'event',
            'type' => 'generation.processing',
            'created_at' => now()->subMinute()->toIso8601String(),
            'data' => ['object' => $this->generationPayload([
                'id' => $generation->provider_generation_id,
                'status' => 'processing',
                'metadata' => ['vellum_generation_id' => $generation->public_id],
            ])],
        ];

        $this->postSignedWebhook($stale, $stale['id'], 'generation.processing')->assertNoContent();

        $generation->refresh();

        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame($chargeId, GenerationCharge::query()->sole()->id);
        $this->assertSame(373_875_000, $generation->actual_provider_cost_nanos);
    }

    public function test_a_failed_generation_is_terminal_and_never_charged(): void
    {
        $generation = Generation::factory()->create([
            'status' => GenerationStatus::Processing,
            'provider_generation_id' => 'gen_test_1234567890abcdef',
        ]);

        $failed = $this->generationPayload([
            'id' => $generation->provider_generation_id,
            'status' => 'failed',
            'metadata' => ['vellum_generation_id' => $generation->public_id],
            'error' => ['code' => 'generation_failed', 'message' => 'The model could not finish.'],
            'completed_at' => now()->toIso8601String(),
        ]);

        $event = [
            'id' => 'evt_failed_0000000000001',
            'object' => 'event',
            'type' => 'generation.failed',
            'data' => ['object' => $failed],
        ];

        $this->postSignedWebhook($event, $event['id'], 'generation.failed')->assertNoContent();

        $generation->refresh();

        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('generation_failed', $generation->failure_code);
        $this->assertNull($generation->actual_provider_cost_nanos);
        $this->assertNull($generation->actual_customer_charge_nanos);
        $this->assertSame(0, GenerationCharge::query()->count());
    }

    public function test_it_rejects_an_unsigned_webhook_without_creating_an_event(): void
    {
        $body = json_encode([
            'id' => 'evt_forged_000000000001',
            'type' => 'ping',
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_EVENT_ID' => 'evt_forged_000000000001',
            'HTTP_PIXELFORGE_EVENT_TYPE' => 'ping',
            'HTTP_PIXELFORGE_SIGNATURE' => 't='.time().',v1=deadbeef',
        ], $body)->assertStatus(400);

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_it_ignores_ping_events(): void
    {
        $event = [
            'id' => 'evt_ping_00000000000001',
            'object' => 'event',
            'type' => 'ping',
            'data' => [],
        ];

        $this->postSignedWebhook($event, $event['id'], 'ping')->assertNoContent();

        $this->assertSame(WebhookEvent::STATUS_IGNORED, WebhookEvent::query()->sole()->status);
    }
}
