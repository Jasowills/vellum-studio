<?php

namespace Tests\Unit;

use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PixelforgeExceptionTest extends TestCase
{
    public function test_it_reads_a_numeric_retry_after_header(): void
    {
        Http::fake([
            'pixelforge.test/v1/generations/*' => Http::response([
                'error' => ['code' => 'rate_limit_exceeded', 'message' => 'Slow down.'],
            ], 429, ['Retry-After' => '42']),
        ]);

        try {
            app(PixelforgeClient::class)->getGeneration('gen_test');
            $this->fail('Expected a Pixelforge exception.');
        } catch (PixelforgeException $exception) {
            $this->assertSame(42, $exception->retryAfter);
            $this->assertTrue($exception->isRetryable());
            $this->assertFalse($exception->isRejection());
        }
    }

    public function test_it_treats_a_daily_limit_as_a_non_retryable_rejection(): void
    {
        Http::fake([
            'pixelforge.test/v1/generations/*' => Http::response([
                'error' => ['code' => 'daily_limit_exceeded', 'message' => 'Quota spent.'],
            ], 429, ['Retry-After' => '60']),
        ]);

        try {
            app(PixelforgeClient::class)->getGeneration('gen_test');
            $this->fail('Expected a Pixelforge exception.');
        } catch (PixelforgeException $exception) {
            $this->assertFalse($exception->isRetryable());
            $this->assertTrue($exception->isRejection());
            $this->assertStringContainsString('00:00 UTC', $exception->userMessage());
        }
    }
}
