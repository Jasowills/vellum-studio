<?php

namespace Tests\Feature;

use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\PixelforgeFixtures;
use Tests\TestCase;

class GenerationSubmissionTest extends TestCase
{
    use PixelforgeFixtures, RefreshDatabase;

    public function test_it_creates_an_idempotent_generation_and_queues_submission(): void
    {
        Http::fake([
            'pixelforge.test/v1/pricing' => Http::response($this->pricingPayload()),
        ]);
        Queue::fake();

        $user = User::factory()->create();
        $attempt = Str::uuid()->toString();
        $payload = [
            'prompt' => 'A quiet observatory above a sea of cloud.',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'attempt_token' => $attempt,
        ];

        $this->actingAs($user)->post('/generations', $payload)
            ->assertRedirect();

        $generation = Generation::query()->sole();

        $this->assertSame('pending', $generation->status->value);
        $this->assertSame('vellum_u'.$user->id.'_'.$attempt, $generation->idempotency_key);
        $this->assertSame($user->id, $generation->user_id);
        $this->assertNull($generation->provider_generation_id);
        $this->assertNotNull($generation->estimate_snapshot['provider_cost_nanos']['max']);
        Queue::assertPushed(SubmitGenerationJob::class, 1);
    }

    public function test_replaying_an_attempt_returns_the_same_generation(): void
    {
        Http::fake([
            'pixelforge.test/v1/pricing' => Http::response($this->pricingPayload()),
        ]);
        Queue::fake();

        $user = User::factory()->create();
        $payload = [
            'prompt' => 'A quiet observatory above a sea of cloud.',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'attempt_token' => Str::uuid()->toString(),
        ];

        $this->actingAs($user)->post('/generations', $payload)->assertRedirect();
        $first = Generation::query()->sole()->public_id;

        $this->actingAs($user)->post('/generations', $payload)
            ->assertRedirect()
            ->assertSessionHas('status', 'Resumed your earlier attempt.');

        $this->assertSame(1, Generation::query()->count());
        $this->assertSame($first, Generation::query()->sole()->public_id);
        Queue::assertPushed(SubmitGenerationJob::class, 2);
    }

    public function test_replaying_an_attempt_with_different_settings_is_rejected(): void
    {
        Http::fake([
            'pixelforge.test/v1/pricing' => Http::response($this->pricingPayload()),
        ]);
        Queue::fake();

        $user = User::factory()->create();
        $attempt = Str::uuid()->toString();
        $payload = [
            'prompt' => 'A quiet observatory above a sea of cloud.',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'attempt_token' => $attempt,
        ];

        $this->actingAs($user)->post('/generations', $payload)->assertRedirect();

        $this->actingAs($user)->from('/studio')->post('/generations', [
            ...$payload,
            'quality' => 'high',
        ])->assertRedirect('/studio')->assertSessionHasErrors('prompt');

        $this->assertSame(1, Generation::query()->count());
    }

    public function test_it_validates_generation_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/studio')->post('/generations', [
            'prompt' => str_repeat('a', 4001),
            'size' => '4096x4096',
            'quality' => 'ultra',
            'reference_images' => array_fill(0, 5, 'https://example.test/image.png'),
            'attempt_token' => 'not-a-uuid',
        ])->assertRedirect('/studio')->assertSessionHasErrors([
            'prompt', 'size', 'quality', 'reference_images', 'attempt_token',
        ]);
    }
}
