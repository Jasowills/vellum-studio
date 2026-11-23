<?php

namespace App\Console\Commands;

use App\Services\Pixelforge\PixelforgeException;
use App\Services\Pixelforge\UsageReconciler;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ReconcileProviderUsage extends Command
{
    protected $signature = 'pixelforge:reconcile-usage
                            {--days=14 : Number of UTC days to compare, including today}
                            {--fail-on-mismatch : Exit non-zero when local and provider totals differ}';

    protected $description = 'Compare the Pixelforge usage report with Vellum\'s charge ledger';

    public function handle(UsageReconciler $reconciler): int
    {
        $days = max(1, min(92, (int) $this->option('days')));
        $end = CarbonImmutable::now('UTC')->startOfDay();
        $start = $end->subDays($days - 1);

        try {
            $report = $reconciler->compare($start, $end);
        } catch (PixelforgeException $exception) {
            $this->components->error('Could not fetch Pixelforge usage: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Range', $report['start_date'].' to '.$report['end_date']);
        $this->components->twoColumnDetail('Provider generations', (string) $report['provider']['generations']);
        $this->components->twoColumnDetail('Local generations', (string) $report['local']['generations']);
        $this->components->twoColumnDetail('Provider cost', $report['provider']['cost']);
        $this->components->twoColumnDetail('Local cost', $report['local']['cost']);
        $this->components->twoColumnDetail(
            'Difference',
            Money::formatExact($report['local']['cost_nanos'] - $report['provider']['cost_nanos']),
        );

        if ($report['matches']) {
            $this->components->info('Provider usage and the local ledger match exactly.');

            return self::SUCCESS;
        }

        $this->components->warn("{$this->count($report['differences'])} day(s) differ from Pixelforge.");

        $this->table(
            ['Date', 'Provider cost', 'Local cost', 'Difference', 'Provider gens', 'Local gens'],
            array_map(fn (array $row) => [
                $row['date'],
                Money::formatExact($row['provider']['cost_nanos']),
                Money::formatExact($row['local']['cost_nanos']),
                Money::formatExact($row['cost_difference_nanos']),
                $row['provider']['generations'],
                $row['local']['generations'],
            ], $report['differences']),
        );

        return $this->option('fail-on-mismatch') ? self::FAILURE : self::SUCCESS;
    }

    private function count(array $rows): int
    {
        return count($rows);
    }
}
