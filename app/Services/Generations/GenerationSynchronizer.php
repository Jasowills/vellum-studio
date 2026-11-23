<?php

namespace App\Services\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\ArchiveGenerationImageJob;
use App\Models\Generation;
use App\Models\GenerationCharge;
use App\Services\Pixelforge\PixelforgeGeneration;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GenerationSynchronizer
{
    public function __construct(private readonly GenerationStateMachine $states) {}

    /**
     * Apply an authoritative Pixelforge generation snapshot. Safe to call for
     * webhooks, reconciliation, and the create response.
     *
     * @return bool whether a meaningful state or financial change was made
     */
    public function apply(Generation $generation, PixelforgeGeneration $provider, string $source): bool
    {
        $archiveId = null;

        $changed = DB::transaction(function () use ($generation, $provider, $source, &$archiveId): bool {
            $local = Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();

            $this->guardIdentity($local, $provider);

            if ($local->provider_generation_id === null) {
                $local->provider_generation_id = $provider->id;
            }

            if ($provider->createdAt !== null && $local->provider_created_at === null) {
                $local->provider_created_at = $provider->createdAt;
            }

            if ($provider->startedAt !== null && $local->processing_at === null) {
                $local->processing_at = $provider->startedAt;
            }

            if ($provider->rates !== null) {
                $snapshot = $local->pricing_snapshot;
                $snapshot['rates'] = $provider->rates;
                $snapshot['rates_source'] = 'provider_generation';
                $local->pricing_snapshot = $snapshot;
            }

            $canRecover = $local->isRecoverableUnconfirmed();
            $statusChanged = false;

            switch ($provider->status) {
                case GenerationStatus::Queued:
                    if (($this->states->canAdvance($local->status, GenerationStatus::Queued) || $canRecover)) {
                        $local->status = GenerationStatus::Queued;
                        $local->failure_code = null;
                        $local->failure_message = null;
                        $local->failure_stage = null;
                        $local->provider_error = null;
                        $local->next_reconcile_at = now()->addSeconds((int) config('pixelforge.reconcile_first_delay', 30));
                        $statusChanged = true;
                    }
                    break;

                case GenerationStatus::Processing:
                    if ($this->states->canAdvance($local->status, GenerationStatus::Processing) || $canRecover) {
                        $local->status = GenerationStatus::Processing;
                        $local->processing_at ??= $provider->startedAt ?? now();
                        $local->failure_code = null;
                        $local->failure_message = null;
                        $local->failure_stage = null;
                        $local->provider_error = null;
                        $local->next_reconcile_at = now()->addSeconds((int) config('pixelforge.reconcile_first_delay', 30));
                        $statusChanged = true;
                    }
                    break;

                case GenerationStatus::Succeeded:
                    if ($local->status === GenerationStatus::Succeeded) {
                        // A duplicate success is harmless. Refresh a missing image
                        // pointer but never touch the settled financial record.
                        if ($local->image_url === null && $provider->imageUrl !== null) {
                            $local->image_url = $provider->imageUrl;
                            $local->image_url_expires_at = $provider->imageExpiresAt;
                        }
                        break;
                    }

                    if ($local->status === GenerationStatus::Failed && ! $canRecover) {
                        $this->logConflict($local, $provider, $source);

                        break;
                    }

                    $this->settle($local, $provider, $source);
                    $statusChanged = true;
                    $archiveId = $local->id;
                    break;

                case GenerationStatus::Failed:
                    if ($local->status->isTerminal()) {
                        if ($local->status !== GenerationStatus::Failed) {
                            $this->logConflict($local, $provider, $source);
                        }

                        break;
                    }

                    $local->status = GenerationStatus::Failed;
                    $local->failure_code = is_string($provider->error['code'] ?? null) ? $provider->error['code'] : 'generation_failed';
                    $local->failure_message = is_string($provider->error['message'] ?? null)
                        ? $provider->error['message']
                        : 'Pixelforge could not complete this generation.';
                    $local->failure_stage = 'generation';
                    $local->provider_error = $provider->error;
                    $local->actual_provider_cost_nanos = null;
                    $local->actual_customer_charge_nanos = null;
                    $local->completed_at = $provider->completedAt ?? now();
                    $local->next_reconcile_at = null;
                    $statusChanged = true;
                    break;

                case GenerationStatus::Pending:
                    break;
            }

            if ($local->isDirty()) {
                $local->save();
            }

            return $statusChanged;
        });

        if ($archiveId !== null) {
            ArchiveGenerationImageJob::dispatch($archiveId)->afterCommit();
        }

        return $changed;
    }

    private function settle(Generation $local, PixelforgeGeneration $provider, string $source): void
    {
        if ($provider->usage === null || $provider->rates === null) {
            throw new RuntimeException('A succeeded Pixelforge generation arrived without usage or rates.');
        }

        $providerCost = Money::providerCostNanos($provider->usage, $provider->rates);
        $customerCharge = Money::customerChargeNanos($providerCost);

        $existing = GenerationCharge::query()->where('generation_id', $local->id)->first();

        if ($existing !== null) {
            if ($existing->provider_cost_nanos !== $providerCost || $existing->customer_charge_nanos !== $customerCharge) {
                Log::critical('Settlement conflict: existing charge differs from provider snapshot.', [
                    'generation' => $local->public_id,
                    'existing_provider_nanos' => $existing->provider_cost_nanos,
                    'new_provider_nanos' => $providerCost,
                ]);
            }
        } else {
            try {
                GenerationCharge::create([
                    'generation_id' => $local->id,
                    'user_id' => $local->user_id,
                    'currency' => 'USD',
                    'input_text_tokens' => $provider->usage['input_text_tokens'],
                    'input_image_tokens' => $provider->usage['input_image_tokens'],
                    'output_tokens' => $provider->usage['output_tokens'],
                    'reasoning_tokens' => $provider->usage['reasoning_tokens'],
                    'total_tokens' => $provider->usage['total_tokens'],
                    'provider_cost_nanos' => $providerCost,
                    'customer_charge_nanos' => $customerCharge,
                    'rates_snapshot' => $provider->rates,
                    'settled_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another worker settled first. The unique index is the final
                // defence against duplicate charges.
                Log::warning('Settlement was already recorded by another worker.', [
                    'generation' => $local->public_id,
                ]);
            }
        }

        $local->status = GenerationStatus::Succeeded;
        $local->input_text_tokens = $provider->usage['input_text_tokens'];
        $local->input_image_tokens = $provider->usage['input_image_tokens'];
        $local->output_tokens = $provider->usage['output_tokens'];
        $local->reasoning_tokens = $provider->usage['reasoning_tokens'];
        $local->total_tokens = $provider->usage['total_tokens'];
        $local->actual_provider_cost_nanos = $providerCost;
        $local->actual_customer_charge_nanos = $customerCharge;
        $local->image_url = $provider->imageUrl;
        $local->image_url_expires_at = $provider->imageExpiresAt;
        $local->completed_at = $provider->completedAt ?? now();
        $local->settled_at = now();
        $local->failure_code = null;
        $local->failure_message = null;
        $local->failure_stage = null;
        $local->provider_error = null;
        $local->next_reconcile_at = null;
        $local->next_submit_at = null;

        Log::info('Generation settled.', [
            'generation' => $local->public_id,
            'source' => $source,
            'provider_cost_nanos' => $providerCost,
            'customer_charge_nanos' => $customerCharge,
            'markup_percent' => Money::MARKUP_PERCENT,
        ]);
    }

    private function guardIdentity(Generation $local, PixelforgeGeneration $provider): void
    {
        if ($local->provider_generation_id !== null && $local->provider_generation_id !== $provider->id) {
            throw new RuntimeException('Provider generation id does not match the local generation.');
        }

        $metadataId = $provider->metadataValue('vellum_generation_id');

        if ($metadataId !== null && $metadataId !== $local->public_id) {
            throw new RuntimeException('Provider metadata points to a different local generation.');
        }

        $duplicate = Generation::query()
            ->where('provider_generation_id', $provider->id)
            ->whereKeyNot($local->getKey())
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Provider generation is already linked to another local generation.');
        }
    }

    private function logConflict(Generation $local, PixelforgeGeneration $provider, string $source): void
    {
        Log::warning('Ignored a conflicting terminal provider event.', [
            'generation' => $local->public_id,
            'local_status' => $local->status->value,
            'provider_status' => $provider->status->value,
            'source' => $source,
        ]);
    }
}
