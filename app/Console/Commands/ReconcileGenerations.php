<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Jobs\ReconcileGenerationJob;
use App\Jobs\SubmitGenerationJob;
use App\Models\Generation;
use Illuminate\Console\Command;

class ReconcileGenerations extends Command
{
    protected $signature = 'vellum:reconcile-generations {--limit=50}';

    protected $description = 'Submit unsent generations and poll Pixelforge for active or unconfirmed ones';

    public function handle(): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $submitted = 0;
        $reconciled = 0;

        $pending = Generation::query()
            ->where('status', GenerationStatus::Pending)
            ->whereNull('provider_generation_id')
            ->where(function ($query) {
                $query->whereNull('next_submit_at')
                    ->orWhere('next_submit_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($pending as $generation) {
            SubmitGenerationJob::dispatch($generation->id);
            $submitted++;
        }

        $due = Generation::query()
            ->where(function ($query) {
                $query->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
                    ->where(function ($inner) {
                        $inner->whereNull('next_reconcile_at')
                            ->orWhere('next_reconcile_at', '<=', now());
                    });
            })
            ->orWhere(function ($query) {
                $query->where('status', GenerationStatus::Failed)
                    ->where('failure_code', 'provider_unconfirmed')
                    ->whereDoesntHave('charge')
                    ->where(function ($inner) {
                        $inner->whereNull('next_reconcile_at')
                            ->orWhere('next_reconcile_at', '<=', now());
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($due as $generation) {
            ReconcileGenerationJob::dispatch($generation->id);
            $reconciled++;
        }

        $this->components->info("Dispatched {$submitted} submission(s) and {$reconciled} reconciliation(s).");

        return self::SUCCESS;
    }
}
