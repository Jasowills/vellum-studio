<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\Generations\ImageArchiver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ArchiveGenerationImageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly int $generationId) {}

    public function handle(ImageArchiver $archiver): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation === null || $generation->status->value !== 'succeeded') {
            return;
        }

        try {
            $archiver->archive($generation);
        } catch (\Throwable $exception) {
            Log::warning('Could not archive generation image.', [
                'generation' => $generation->public_id,
                'error' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
