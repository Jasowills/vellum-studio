<?php

namespace App\Services\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\EstimateService;
use App\Services\Pixelforge\PricingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateGeneration
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly EstimateService $estimates,
    ) {}

    /**
     * @return array{generation: Generation, created: bool}
     */
    public function handle(User $user, array $input): array
    {
        $pricing = $this->pricing->current();
        $referenceImages = array_values($input['reference_images'] ?? []);

        $estimate = $this->estimates->estimate(
            $pricing,
            $input['prompt'],
            $input['size'],
            $input['quality'],
            $referenceImages,
        );

        $idempotencyKey = $this->idempotencyKey($user, $input['attempt_token']);
        $requestHash = $this->requestHash($input, $referenceImages);

        try {
            [$generation, $created] = DB::transaction(function () use ($user, $input, $referenceImages, $pricing, $estimate, $idempotencyKey, $requestHash): array {
                $existing = Generation::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->guardReplay($existing, $user, $requestHash);

                    return [$existing, false];
                }

                $publicId = (string) Str::ulid();

                $generation = Generation::create([
                    'user_id' => $user->id,
                    'public_id' => $publicId,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'status' => GenerationStatus::Pending,
                    'prompt' => $input['prompt'],
                    'size' => $input['size'],
                    'quality' => $input['quality'],
                    'reference_images' => $referenceImages,
                    'metadata' => ['vellum_generation_id' => $publicId],
                    'pricing_snapshot' => [
                        ...$pricing->toArray(),
                        'rates_source' => 'pricing_endpoint',
                    ],
                    'estimate_snapshot' => [
                        ...$estimate,
                        'method_version' => 1,
                    ],
                    'estimated_provider_cost_min_nanos' => $estimate['provider_cost_nanos']['min'],
                    'estimated_provider_cost_max_nanos' => $estimate['provider_cost_nanos']['max'],
                    'estimated_customer_charge_min_nanos' => $estimate['customer_charge_nanos']['min'],
                    'estimated_customer_charge_max_nanos' => $estimate['customer_charge_nanos']['max'],
                    'next_submit_at' => now()->addMinutes(2),
                ]);

                return [$generation, true];
            });
        } catch (UniqueConstraintViolationException) {
            $generation = Generation::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
            $this->guardReplay($generation, $user, $requestHash);
            $created = false;
        }

        if ($created || ($generation->status === GenerationStatus::Pending && $generation->provider_generation_id === null)) {
            $this->dispatchSubmission($generation);
        }

        return ['generation' => $generation, 'created' => $created];
    }

    private function dispatchSubmission(Generation $generation): void
    {
        try {
            SubmitGenerationJob::dispatch($generation->id);
        } catch (Throwable $exception) {
            // The local record and its idempotency key are already durable.
            // The scheduler will submit it if the queue was unavailable.
            Log::error('Could not enqueue generation submission.', [
                'generation' => $generation->public_id,
                'error' => $exception::class,
            ]);
        }
    }

    private function guardReplay(Generation $generation, User $user, string $requestHash): void
    {
        if ($generation->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'prompt' => 'This attempt could not be resumed. Start a new generation.',
            ]);
        }

        if (! hash_equals($generation->request_hash, $requestHash)) {
            throw ValidationException::withMessages([
                'prompt' => 'This attempt was already used with different settings. Start a new generation.',
            ]);
        }
    }

    private function idempotencyKey(User $user, string $attemptToken): string
    {
        return 'vellum_u'.$user->id.'_'.$attemptToken;
    }

    private function requestHash(array $input, array $referenceImages): string
    {
        return hash('sha256', json_encode([
            'prompt' => $input['prompt'],
            'size' => $input['size'],
            'quality' => $input['quality'],
            'reference_images' => $referenceImages,
        ], JSON_THROW_ON_ERROR));
    }
}
