<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\GenerationCharge;
use App\Services\Pixelforge\UsageReconciler;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PixelforgeFixtures;
use Tests\TestCase;

class UsageReconciliationTest extends TestCase
{
    use PixelforgeFixtures, RefreshDatabase;

    public function test_it_reconciles_provider_usage_with_the_local_charge_ledger(): void
    {
        $usage = $this->usage();
        $providerCost = Money::providerCostNanos($usage, $this->rates());
        $day = '2026-11-20';

        $generation = Generation::factory()->succeeded()->create([
            'completed_at' => $day.' 12:00:00',
        ]);

        GenerationCharge::factory()->create([
            'generation_id' => $generation->id,
            'user_id' => $generation->user_id,
            ...$usage,
            'provider_cost_nanos' => $providerCost,
            'customer_charge_nanos' => Money::customerChargeNanos($providerCost),
        ]);

        Http::fake([
            'pixelforge.test/v1/usage*' => Http::response([
                'data' => [[
                    'date' => $day,
                    'generations' => 1,
                    ...$usage,
                    'cost_usd' => Money::usdString($providerCost),
                ]],
                'total' => [
                    'generations' => 1,
                    'cost_usd' => Money::usdString($providerCost),
                ],
            ]),
        ]);

        $report = app(UsageReconciler::class)->compare(
            CarbonImmutable::parse($day),
            CarbonImmutable::parse($day),
        );

        $this->assertTrue($report['matches']);
        $this->assertSame([], $report['differences']);
        $this->assertSame(1, $report['provider']['generations']);
        $this->assertSame(1, $report['local']['generations']);
        $this->assertSame($providerCost, $report['provider']['cost_nanos']);
        $this->assertSame($providerCost, $report['local']['cost_nanos']);

        Http::assertSent(fn ($request) => $request->url() === 'https://pixelforge.test/v1/usage?start_date='.$day.'&end_date='.$day);
    }

    public function test_it_reports_cost_mismatches_instead_of_silently_passing(): void
    {
        $usage = $this->usage();
        $localCost = Money::providerCostNanos($usage, $this->rates());
        $providerCost = $localCost + 1;
        $day = '2026-11-20';

        $generation = Generation::factory()->succeeded()->create([
            'completed_at' => $day.' 12:00:00',
        ]);

        GenerationCharge::factory()->create([
            'generation_id' => $generation->id,
            'user_id' => $generation->user_id,
            ...$usage,
            'provider_cost_nanos' => $localCost,
            'customer_charge_nanos' => Money::customerChargeNanos($localCost),
        ]);

        Http::fake([
            'pixelforge.test/v1/usage*' => Http::response([
                'data' => [[
                    'date' => $day,
                    'generations' => 1,
                    ...$usage,
                    'cost_usd' => Money::usdString($providerCost),
                ]],
                'total' => [],
            ]),
        ]);

        $report = app(UsageReconciler::class)->compare(
            CarbonImmutable::parse($day),
            CarbonImmutable::parse($day),
        );

        $this->assertFalse($report['matches']);
        $this->assertCount(1, $report['differences']);
        $this->assertSame(-1, $report['differences'][0]['cost_difference_nanos']);
        $this->assertSame(0, $report['differences'][0]['token_difference']);
    }
}
