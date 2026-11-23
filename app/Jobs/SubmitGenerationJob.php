<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Generations\GenerationSynchronizer;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitGenerationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly int $generationId) {}

    public function handle(PixelforgeClient $client, GenerationSynchronizer $synchronizer): void
    {
        $lock = Cache::lock('pixelforge:submit:'.$this->generationId, 120);

        if (! $lock->get()) {
            self::dispatch($this->generationId)->delay(now()->addSeconds(5));

            return;
        }

        try {
            $generation = Generation::query()->find($this->generationId);

            if ($generation === null || $generation->status->isTerminal() || $generation->provider_generation_id !== null) {
                return;
            }

            $generation->forceFill([
                'submit_attempts' => $generation->submit_attempts + 1,
                'next_submit_at' => null,
            ])->save();

            $provider = $client->createGeneration(
                prompt: $generation->prompt,
                size: $generation->size,
                quality: $generation->quality,
                referenceImages: $generation->reference_images,
                metadata: ['vellum_generation_id' => $generation->public_id],
                idempotencyKey: $generation->idempotency_key,
            );

            $synchronizer->apply($generation, $provider, 'create_response');
        } catch (PixelforgeException $exception) {
            $this->handleProviderFailure($generation ?? null, $exception);
        } catch (Throwable $exception) {
            Log::error('Unexpected generation submission failure.', [
                'generation_id' => $this->generationId,
                'error' => $exception::class,
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function handleProviderFailure(?Generation $generation, PixelforgeException $exception): void
    {
        if ($generation === null) {
            return;
        }

        $generation->refresh();

        if ($generation->status->isTerminal() || $generation->provider_generation_id !== null) {
            return;
        }

        Log::warning('Pixelforge generation submission failed.', [
            'generation' => $generation->public_id,
            'status' => $exception->status,
            'code' => $exception->errorCode,
            'retryable' => $exception->isRetryable(),
        ]);

        if ($exception->isRejection()) {
            if ($exception->errorCode === 'invalid_api_key') {
                Log::critical('Pixelforge rejected the configured API key.');
            }

            $this->markFailed($generation, $exception->errorCode, $exception);

            return;
        }

        $maxAttempts = (int) config('pixelforge.submit_max_attempts', 12);
        $maxAge = (int) config('pixelforge.submit_max_age_minutes', 30);
        $attempt = $generation->submit_attempts;

        if ($attempt >= $maxAttempts || $generation->created_at->diffInMinutes(now()) >= $maxAge) {
            $this->markUnconfirmed($generation, $exception);

            return;
        }

        $delay = max($exception->retryAfter ?? 0, $this->retryDelay($attempt));
        $generation->forceFill(['next_submit_at' => now()->addSeconds($delay)])->save();

        if ($generation->status === GenerationStatus::Pending) {
            self::dispatch($generation->id)->delay(now()->addSeconds($delay));
        }
    }

    private function markFailed(Generation $generation, string $code, PixelforgeException $exception): void
    {
        $generation->forceFill([
            'status' => GenerationStatus::Failed,
            'failure_code' => $code,
            'failure_message' => $exception->userMessage(),
            'failure_stage' => 'rejected',
            'provider_error' => [
                'status' => $exception->status,
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
                'param' => $exception->param,
            ],
            'completed_at' => now(),
            'next_submit_at' => null,
            'next_reconcile_at' => null,
        ])->save();
    }

    private function markUnconfirmed(Generation $generation, PixelforgeException $exception): void
    {
        $generation->forceFill([
            'status' => GenerationStatus::Failed,
            'failure_code' => 'provider_unconfirmed',
            'failure_message' => $exception->userMessage(),
            'failure_stage' => 'submission',
            'provider_error' => [
                'status' => $exception->status,
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ],
            'completed_at' => now(),
            'next_submit_at' => null,
            'next_reconcile_at' => now()->addMinutes(15),
            'reconcile_attempts' => 0,
        ])->save();
    }

    private function retryDelay(int $attempt): int
    {
        $delays = [3, 5, 10, 20, 30, 60, 120, 180, 300, 300, 300, 300];

        return $delays[min(max(0, $attempt - 1), count($delays) - 1)];
    }
}
