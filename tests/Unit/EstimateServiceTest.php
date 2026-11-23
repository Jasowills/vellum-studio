<?php

namespace Tests\Unit;

use App\Services\Pixelforge\EstimateService;
use App\Services\Pixelforge\PricingTable;
use Database\Factories\GenerationFactory;
use Tests\TestCase;

class EstimateServiceTest extends TestCase
{
    public function test_it_estimates_the_documented_maximum_range(): void
    {
        $pricing = PricingTable::fromPayload([
            'rates' => GenerationFactory::pricingSnapshot()['rates'],
            'tokens' => GenerationFactory::pricingSnapshot()['tokens'],
        ], '2026-11-23T00:00:00+00:00');

        $estimate = app(EstimateService::class)->estimate(
            $pricing,
            str_repeat('a', 400),
            '1536x1024',
            'high',
            ['https://example.test/one.png', 'https://example.test/two.png'],
        );

        $this->assertSame(400, $estimate['prompt_characters']);
        $this->assertSame(100, $estimate['text_tokens']['base']);
        $this->assertSame(85, $estimate['text_tokens']['min']);
        $this->assertSame(115, $estimate['text_tokens']['max']);
        $this->assertSame(1530, $estimate['input_image_tokens']);
        $this->assertSame(6450, $estimate['output_tokens']);
        $this->assertSame(['min' => 1000, 'max' => 8000], $estimate['reasoning_tokens']);

        $this->assertSame(286_225_000, $estimate['provider_cost_nanos']['min']);
        $this->assertSame(373_875_000, $estimate['provider_cost_nanos']['max']);
        $this->assertSame(357_781_250, $estimate['customer_charge_nanos']['min']);
        $this->assertSame(467_343_750, $estimate['customer_charge_nanos']['max']);
        $this->assertSame(25, $estimate['markup_percent']);
    }

    public function test_it_rejects_unsupported_options(): void
    {
        $pricing = PricingTable::fromPayload([
            'rates' => GenerationFactory::pricingSnapshot()['rates'],
            'tokens' => GenerationFactory::pricingSnapshot()['tokens'],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(EstimateService::class)->estimate($pricing, 'hello', '2048x2048', 'high', []);
    }
}
