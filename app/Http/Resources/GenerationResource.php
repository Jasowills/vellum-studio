<?php

namespace App\Http\Resources;

use App\Models\Generation;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = (bool) $request->user()?->is_admin;
        $imageAvailable = $this->archived_image_path !== null
            || ($this->image_url !== null
                && ($this->image_url_expires_at === null || $this->image_url_expires_at->isFuture()));

        $estimate = $this->estimate_snapshot ?? [];
        $estimateCustomer = $estimate['customer_charge_nanos'] ?? [
            'min' => $this->estimated_customer_charge_min_nanos,
            'max' => $this->estimated_customer_charge_max_nanos,
        ];

        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'timeline' => [
                'submitted_at' => $this->created_at?->toIso8601String(),
                'accepted_at' => $this->provider_created_at?->toIso8601String(),
                'processing_at' => $this->processing_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'settled_at' => $this->settled_at?->toIso8601String(),
            ],
            'image' => [
                'url' => $imageAvailable ? route('generations.image', ['generation' => $this->public_id]) : null,
                'available' => $imageAvailable,
                'expired' => $this->status->value === 'succeeded' && ! $imageAvailable,
                'expires_at' => $this->image_url_expires_at?->toIso8601String(),
            ],
            'estimate' => [
                'customer_charge_nanos' => [
                    'min' => $estimateCustomer['min'] ?? null,
                    'max' => $estimateCustomer['max'] ?? null,
                ],
                'customer_charge' => [
                    'min' => isset($estimateCustomer['min']) ? Money::formatUsd((int) $estimateCustomer['min']) : null,
                    'max' => isset($estimateCustomer['max']) ? Money::formatUsd((int) $estimateCustomer['max']) : null,
                ],
                'total_tokens' => $estimate['total_tokens'] ?? null,
                'markup_percent' => $estimate['markup_percent'] ?? Money::MARKUP_PERCENT,
                'pricing_fetched_at' => $this->pricing_snapshot['fetched_at'] ?? null,
            ],
            'cost' => [
                'currency' => 'USD',
                'customer_charge_nanos' => $this->actual_customer_charge_nanos,
                'customer_charge' => $this->actual_customer_charge_nanos !== null
                    ? Money::formatUsd($this->actual_customer_charge_nanos)
                    : null,
                'customer_charge_exact' => $this->actual_customer_charge_nanos !== null
                    ? Money::formatExact($this->actual_customer_charge_nanos)
                    : null,
            ],
            'usage' => [
                'input_text_tokens' => $this->input_text_tokens,
                'input_image_tokens' => $this->input_image_tokens,
                'output_tokens' => $this->output_tokens,
                'reasoning_tokens' => $this->reasoning_tokens,
                'total_tokens' => $this->total_tokens,
            ],
            'failure' => $this->status->value === 'failed' ? [
                'code' => $this->failure_code,
                'message' => $this->failure_message,
                'stage' => $this->failure_stage,
            ] : null,
            'charge' => $this->whenLoaded('charge', fn () => [
                'customer_charge_nanos' => $this->charge->customer_charge_nanos,
                'customer_charge' => Money::formatUsd($this->charge->customer_charge_nanos),
                'customer_charge_exact' => Money::formatExact($this->charge->customer_charge_nanos),
                'settled_at' => $this->charge->settled_at?->toIso8601String(),
                'tokens' => [
                    'input_text_tokens' => $this->charge->input_text_tokens,
                    'input_image_tokens' => $this->charge->input_image_tokens,
                    'output_tokens' => $this->charge->output_tokens,
                    'reasoning_tokens' => $this->charge->reasoning_tokens,
                    'total_tokens' => $this->charge->total_tokens,
                ],
            ]),
            'can_retry' => $this->status->value === 'failed',
            'prefill_url' => route('studio', ['from' => $this->public_id]),
            'provider' => $this->when($isAdmin, fn () => [
                'generation_id' => $this->provider_generation_id,
                'provider_cost_nanos' => $this->actual_provider_cost_nanos,
                'provider_cost' => $this->actual_provider_cost_nanos !== null
                    ? Money::formatExact($this->actual_provider_cost_nanos)
                    : null,
                'submit_attempts' => $this->submit_attempts,
                'reconcile_attempts' => $this->reconcile_attempts,
                'error' => $this->provider_error,
            ]),
        ];
    }
}
