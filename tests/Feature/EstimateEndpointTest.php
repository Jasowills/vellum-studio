<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PixelforgeFixtures;
use Tests\TestCase;

class EstimateEndpointTest extends TestCase
{
    use PixelforgeFixtures, RefreshDatabase;

    public function test_it_returns_a_live_documented_pricing_estimate(): void
    {
        Http::fake([
            'pixelforge.test/v1/pricing' => Http::response($this->pricingPayload()),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/studio/estimate', [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.test/one.png', 'https://example.test/two.png'],
        ]);

        $response->assertOk()
            ->assertJsonPath('customer_charge_nanos.min', 357_781_250)
            ->assertJsonPath('customer_charge_nanos.max', 467_343_750)
            ->assertJsonPath('output_tokens', 6450)
            ->assertJsonPath('input_image_tokens', 1530)
            ->assertJsonPath('markup_percent', 25);

        Http::assertSentCount(1);
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->postJson('/studio/estimate', [
            'prompt' => 'A quiet room.',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
        ]);

        $response->assertUnauthorized();
        Http::assertNothingSent();
    }
}
