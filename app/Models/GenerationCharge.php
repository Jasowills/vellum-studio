<?php

namespace App\Models;

use Database\Factories\GenerationChargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable financial ledger row. There is at most one charge per generation
 * (enforced by a unique index), and it is only written when a provider success
 * is settled. Failed generations never produce a charge.
 */
class GenerationCharge extends Model
{
    /** @use HasFactory<GenerationChargeFactory> */
    use HasFactory;

    protected $fillable = [
        'generation_id',
        'user_id',
        'currency',
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
        'total_tokens',
        'provider_cost_nanos',
        'customer_charge_nanos',
        'rates_snapshot',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'input_text_tokens' => 'integer',
            'input_image_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'total_tokens' => 'integer',
            'provider_cost_nanos' => 'integer',
            'customer_charge_nanos' => 'integer',
            'rates_snapshot' => 'array',
            'settled_at' => 'immutable_datetime',
        ];
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
