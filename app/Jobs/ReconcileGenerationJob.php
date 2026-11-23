<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Generations\GenerationSynchronizer;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconcileGenerationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly int $generationId) {}

    public function handle(PixelforgeClient $client, GenerationSynchronizer $synchronizer): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation === null) {
            return;
        }

        if ($generation->status->isTerminal() && ! $generation->isRecoverableUnconfirmed()) {
            return;
        }

        $attempt = $generation->reconcile_attempts + 1;
        $generation->forceFill([
            'reconcile_attempts' => $attempt,
            'next_reconcile_at' => now()->addSeconds($this->nextDelay($attempt)),
        ])->save();

        try {
            $provider = $generation->provider_generation_id !== null
                ? $client->getGeneration($generation->provider_generation_id)
                : $client->createGeneration(
                    prompt: $generation->prompt,
                    size: $generation->size,
                    quality: $generation->quality,
                    referenceImages: $generation->reference_images,
                    metadata: ['vellum_generation_id' => $generation->public_id],
                    idempotencyKey: $generation->idempotency_key,
                );

            $synchronizer->apply($generation, $provider, 'reconciliation');
        } catch (PixelforgeException $exception) {
            $this->handleFailure($generation, $exception, $attempt);
        }
    }

    private function handleFailure(Generation $generation, PixelforgeException $exception, int $attempt): void
    {
        Log::warning('Generation reconciliation failed.', [
            'generation' => $generation->public_id,
            'code' => $exception->errorCode,
            'status' => $exception->status,
            'attempt' => $attempt,
        ]);

        if ($exception->errorCode === 'not_found' && $attempt >= 3) {
            $generation->forceFill([
                'status' => GenerationStatus::Failed,
                'failure_code' => 'not_found',
                'failure_message' => 'Pixelforge could not find this generation. Nothing was charged.',
                'failure_stage' => 'reconciliation',
                'provider_error' => ['status' => $exception->status, 'code' => $exception->errorCode],
                'completed_at' => now(),
                'next_reconcile_at' => null,
            ])->save();

            return;
        }

        if ($exception->isRejection()) {
            $generation->forceFill([
                'status' => GenerationStatus::Failed,
                'failure_code' => $exception->errorCode,
                'failure_message' => $exception->userMessage(),
                'failure_stage' => 'reconciliation',
                'provider_error' => ['status' => $exception->status, 'code' => $exception->errorCode],
                'completed_at' => now(),
                'next_reconcile_at' => null,
            ])->save();

            return;
        }

        $age = $generation->created_at->diffInMinutes(now());

        if ($age >= (int) config('pixelforge.reconcile_give_up_minutes', 60)) {
            $generation->forceFill([
                'status' => GenerationStatus::Failed,
                'failure_code' => 'provider_timeout',
                'failure_message' => 'Pixelforge did not confirm this generation in time. Nothing was charged. If it finishes later, this record will be updated.',
                'failure_stage' => 'reconciliation',
                'provider_error' => ['status' => $exception->status, 'code' => $exception->errorCode],
                'completed_at' => now(),
                'next_reconcile_at' => now()->addMinutes(30),
            ])->save();
        }
    }

    private function nextDelay(int $attempt): int
    {
        $first = (int) config('pixelforge.reconcile_first_delay', 30);
        $max = (int) config('pixelforge.reconcile_max_delay', 300);

        return min($max, $first * (2 ** max(0, $attempt - 1)));
    }
}
