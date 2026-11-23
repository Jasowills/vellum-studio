<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GenerationChargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable financial ledger row. There is at most one charge per generation
 * (enforced by a unique index), and it is only written when a provider success
 * is settled. Failed generations never produce a charge.
 *
 * @property int $id
 * @property int $generation_id
 * @property int $user_id
 * @property string $currency
 * @property int $input_text_tokens
 * @property int $input_image_tokens
 * @property int $output_tokens
 * @property int $reasoning_tokens
 * @property int $total_tokens
 * @property int $provider_cost_nanos
 * @property int $customer_charge_nanos
 * @property array<string, mixed> $rates_snapshot
 * @property CarbonImmutable|null $settled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Generation|null $generation
 * @property-read User|null $user
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

    /**
     * @return BelongsTo<Generation, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
