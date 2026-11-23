<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'public_id',
        'provider_generation_id',
        'idempotency_key',
        'request_hash',
        'status',
        'prompt',
        'size',
        'quality',
        'reference_images',
        'metadata',
        'pricing_snapshot',
        'estimate_snapshot',
        'estimated_provider_cost_min_nanos',
        'estimated_provider_cost_max_nanos',
        'estimated_customer_charge_min_nanos',
        'estimated_customer_charge_max_nanos',
        'actual_provider_cost_nanos',
        'actual_customer_charge_nanos',
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
        'total_tokens',
        'image_url',
        'image_url_expires_at',
        'archived_image_path',
        'failure_code',
        'failure_message',
        'failure_stage',
        'provider_error',
        'submit_attempts',
        'next_submit_at',
        'reconcile_attempts',
        'next_reconcile_at',
        'provider_created_at',
        'processing_at',
        'completed_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'metadata' => 'array',
            'pricing_snapshot' => 'array',
            'estimate_snapshot' => 'array',
            'provider_error' => 'array',
            'estimated_provider_cost_min_nanos' => 'integer',
            'estimated_provider_cost_max_nanos' => 'integer',
            'estimated_customer_charge_min_nanos' => 'integer',
            'estimated_customer_charge_max_nanos' => 'integer',
            'actual_provider_cost_nanos' => 'integer',
            'actual_customer_charge_nanos' => 'integer',
            'input_text_tokens' => 'integer',
            'input_image_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'total_tokens' => 'integer',
            'submit_attempts' => 'integer',
            'reconcile_attempts' => 'integer',
            'image_url_expires_at' => 'immutable_datetime',
            'next_submit_at' => 'immutable_datetime',
            'next_reconcile_at' => 'immutable_datetime',
            'provider_created_at' => 'immutable_datetime',
            'processing_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function charge(): HasOne
    {
        return $this->hasOne(GenerationCharge::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /**
     * Only a submission whose provider acceptance was never confirmed may be
     * recovered after a local "unconfirmed" failure. Normal failed and
     * succeeded generations are terminal.
     */
    public function isRecoverableUnconfirmed(): bool
    {
        return $this->status === GenerationStatus::Failed
            && $this->failure_code === 'provider_unconfirmed'
            && $this->charge()->doesntExist();
    }

    public function hasImage(): bool
    {
        return $this->archived_image_path !== null || $this->image_url !== null;
    }
}
