<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPixelforgeWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class RetryWebhooks extends Command
{
    protected $signature = 'vellum:retry-webhooks {--limit=50}';

    protected $description = 'Requeue verified webhook events whose processing was deferred or lost';

    public function handle(): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));

        $events = WebhookEvent::query()
            ->where('status', WebhookEvent::STATUS_PENDING)
            ->where('processing_attempts', '<', 8)
            ->where('created_at', '<=', now()->subSeconds(30))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            ProcessPixelforgeWebhookJob::dispatch($event->id);
        }

        $this->components->info("Requeued {$events->count()} webhook event(s).");

        return self::SUCCESS;
    }
}
