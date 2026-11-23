<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\GenerationCharge;
use App\Services\Generations\GenerationSynchronizer;
use App\Services\Pixelforge\PixelforgeGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PixelforgeFixtures;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use PixelforgeFixtures, RefreshDatabase;

    public function test_a_conflicting_duplicate_success_never_creates_a_second_charge(): void
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

        $synchronizer = app(GenerationSynchronizer::class);

        $this->assertTrue($synchronizer->apply($generation, PixelforgeGeneration::fromArray($succeeded), 'test'));
        $this->assertFalse($synchronizer->apply($generation, PixelforgeGeneration::fromArray($succeeded), 'test'));

        $generation->refresh();

        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(1, GenerationCharge::query()->count());
        $this->assertSame(373_875_000, GenerationCharge::query()->sole()->provider_cost_nanos);
        $this->assertSame(467_343_750, GenerationCharge::query()->sole()->customer_charge_nanos);

        $conflicting = $succeeded;
        $conflicting['usage'] = [
            ...$this->usage(),
            'reasoning_tokens' => 9000,
            'total_tokens' => 17095,
        ];

        $synchronizer->apply($generation, PixelforgeGeneration::fromArray($conflicting), 'test');

        $generation->refresh();

        $this->assertSame(1, GenerationCharge::query()->count());
        $this->assertSame(373_875_000, $generation->actual_provider_cost_nanos);
        $this->assertSame(467_343_750, $generation->actual_customer_charge_nanos);
    }

    public function test_it_rejects_a_provider_snapshot_linked_to_another_generation(): void
    {
        $generation = Generation::factory()->create([
            'status' => GenerationStatus::Processing,
            'provider_generation_id' => 'gen_test_1234567890abcdef',
        ]);

        $other = Generation::factory()->create();

        $payload = $this->generationPayload([
            'id' => $generation->provider_generation_id,
            'status' => 'succeeded',
            'metadata' => ['vellum_generation_id' => $other->public_id],
            'usage' => $this->usage(),
            'rates' => $this->rates(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('metadata points to a different local generation');

        app(GenerationSynchronizer::class)->apply(
            $generation,
            PixelforgeGeneration::fromArray($payload),
            'test',
        );
    }
}
