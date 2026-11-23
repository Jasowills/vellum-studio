<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PixelforgeFixtures;
use Tests\TestCase;

class SubmissionJobTest extends TestCase
{
    use PixelforgeFixtures, RefreshDatabase;

    public function test_it_submits_with_the_stable_idempotency_key_and_applies_the_response(): void
    {
        $generation = Generation::factory()->create([
            'status' => GenerationStatus::Pending,
            'provider_generation_id' => null,
            'submit_attempts' => 0,
            'next_submit_at' => null,
        ]);

        Http::fake([
            'pixelforge.test/v1/generations' => Http::response($this->generationPayload([
                'id' => 'gen_created_1234567890',
                'status' => 'queued',
                'metadata' => ['vellum_generation_id' => $generation->public_id],
            ]), 202),
        ]);

        SubmitGenerationJob::dispatch($generation->id);

        $generation->refresh();

        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertSame('gen_created_1234567890', $generation->provider_generation_id);
        $this->assertSame(1, $generation->submit_attempts);
        $this->assertNull($generation->charge);

        Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', $generation->idempotency_key));
    }

    public function test_it_honours_retry_after_but_stops_at_the_bounded_retry_limit(): void
    {
        config(['pixelforge.submit_max_attempts' => 12]);

        $generation = Generation::factory()->create([
            'status' => GenerationStatus::Pending,
            'provider_generation_id' => null,
            'submit_attempts' => 11,
            'next_submit_at' => null,
        ]);

        Http::fake([
            'pixelforge.test/v1/generations' => Http::response([
                'error' => [
                    'code' => 'rate_limit_exceeded',
                    'message' => 'Too many requests.',
                ],
            ], 429, ['Retry-After' => '42']),
        ]);

        SubmitGenerationJob::dispatch($generation->id);

        $generation->refresh();

        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('provider_unconfirmed', $generation->failure_code);
        $this->assertSame('submission', $generation->failure_stage);
        $this->assertNotNull($generation->next_reconcile_at);
        $this->assertNull($generation->next_submit_at);
        $this->assertNull($generation->charge);

        Http::assertSentCount(1);
    }

    public function test_a_provider_rejection_fails_the_generation_without_a_charge(): void
    {
        $generation = Generation::factory()->create([
            'status' => GenerationStatus::Pending,
            'provider_generation_id' => null,
            'submit_attempts' => 0,
        ]);

        Http::fake([
            'pixelforge.test/v1/generations' => Http::response([
                'error' => [
                    'code' => 'content_policy_violation',
                    'message' => 'The prompt was rejected.',
                ],
            ], 400),
        ]);

        SubmitGenerationJob::dispatch($generation->id);

        $generation->refresh();

        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('content_policy_violation', $generation->failure_code);
        $this->assertSame('rejected', $generation->failure_stage);
        $this->assertNull($generation->actual_provider_cost_nanos);
        $this->assertNull($generation->actual_customer_charge_nanos);
        $this->assertNull($generation->charge);

        Http::assertSentCount(1);
    }
}
