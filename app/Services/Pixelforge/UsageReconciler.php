<?php

namespace App\Services\Pixelforge;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Compares the provider's source-of-truth usage report with Vellum's immutable
 * charge ledger. The provider bills on the UTC day a generation completed, so
 * local totals are grouped by the provider's completed_at timestamp, not by
 * the moment our worker happened to settle the charge.
 */
class UsageReconciler
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     provider: array<string, mixed>,
     *     local: array<string, mixed>,
     *     days: array<int, array<string, mixed>>,
     *     differences: array<int, array<string, mixed>>,
     *     matches: bool
     * }
     */
    public function compare(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $provider = $this->client->usage($start->toDateString(), $end->toDateString());
        $providerDays = [];

        foreach ($provider['data'] as $row) {
            $date = (string) ($row['date'] ?? '');

            if ($date === '') {
                continue;
            }

            $inputTextTokens = (int) ($row['input_text_tokens'] ?? 0);
            $inputImageTokens = (int) ($row['input_image_tokens'] ?? 0);
            $outputTokens = (int) ($row['output_tokens'] ?? 0);
            $reasoningTokens = (int) ($row['reasoning_tokens'] ?? 0);

            $providerDays[$date] = [
                'date' => $date,
                'generations' => (int) ($row['generations'] ?? 0),
                'input_text_tokens' => $inputTextTokens,
                'input_image_tokens' => $inputImageTokens,
                'output_tokens' => $outputTokens,
                'reasoning_tokens' => $reasoningTokens,
                'total_tokens' => isset($row['total_tokens'])
                    ? (int) $row['total_tokens']
                    : $inputTextTokens + $inputImageTokens + $outputTokens + $reasoningTokens,
                'cost_nanos' => Money::fromUsdString((string) ($row['cost_usd'] ?? '0')),
            ];
        }

        $localDays = $this->localDays($start, $end);
        $dates = array_values(array_unique([...array_keys($providerDays), ...array_keys($localDays)]));
        sort($dates);

        $days = [];
        $differences = [];

        foreach ($dates as $date) {
            $providerDay = $providerDays[$date] ?? $this->emptyDay($date);
            $localDay = $localDays[$date] ?? $this->emptyDay($date);

            $costDifference = $localDay['cost_nanos'] - $providerDay['cost_nanos'];
            $tokenDifference = $localDay['total_tokens'] - $providerDay['total_tokens'];
            $generationDifference = $localDay['generations'] - $providerDay['generations'];
            $matches = $costDifference === 0 && $tokenDifference === 0 && $generationDifference === 0;

            $row = [
                'date' => $date,
                'provider' => $providerDay,
                'local' => $localDay,
                'cost_difference_nanos' => $costDifference,
                'token_difference' => $tokenDifference,
                'generation_difference' => $generationDifference,
                'matches' => $matches,
            ];

            $days[] = $row;

            if (! $matches) {
                $differences[] = $row;
            }
        }

        $providerTotal = $this->total($providerDays);
        $localTotal = $this->total($localDays);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'provider' => [
                ...$providerTotal,
                'cost_usd' => Money::usdString($providerTotal['cost_nanos']),
                'cost' => Money::formatExact($providerTotal['cost_nanos']),
                'raw_total' => $provider['total'],
            ],
            'local' => [
                ...$localTotal,
                'cost_usd' => Money::usdString($localTotal['cost_nanos']),
                'cost' => Money::formatExact($localTotal['cost_nanos']),
            ],
            'days' => $days,
            'differences' => $differences,
            'matches' => $differences === [],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function localDays(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::table('generation_charges')
            ->join('generations', 'generations.id', '=', 'generation_charges.generation_id')
            ->whereNotNull('generations.completed_at')
            ->whereDate('generations.completed_at', '>=', $start->toDateString())
            ->whereDate('generations.completed_at', '<=', $end->toDateString())
            ->selectRaw('DATE(generations.completed_at) as day')
            ->selectRaw('COUNT(*) as generations')
            ->selectRaw('COALESCE(SUM(generation_charges.input_text_tokens), 0) as input_text_tokens')
            ->selectRaw('COALESCE(SUM(generation_charges.input_image_tokens), 0) as input_image_tokens')
            ->selectRaw('COALESCE(SUM(generation_charges.output_tokens), 0) as output_tokens')
            ->selectRaw('COALESCE(SUM(generation_charges.reasoning_tokens), 0) as reasoning_tokens')
            ->selectRaw('COALESCE(SUM(generation_charges.total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(generation_charges.provider_cost_nanos), 0) as cost_nanos')
            ->groupByRaw('DATE(generations.completed_at)')
            ->orderBy('day')
            ->get();

        $days = [];

        foreach ($rows as $row) {
            $date = (string) $row->day;

            $days[$date] = [
                'date' => $date,
                'generations' => (int) $row->generations,
                'input_text_tokens' => (int) $row->input_text_tokens,
                'input_image_tokens' => (int) $row->input_image_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'reasoning_tokens' => (int) $row->reasoning_tokens,
                'total_tokens' => (int) $row->total_tokens,
                'cost_nanos' => (int) $row->cost_nanos,
            ];
        }

        return $days;
    }

    /**
     * @param  array<string, array<string, mixed>>  $days
     * @return array<string, int>
     */
    private function total(array $days): array
    {
        $total = [
            'generations' => 0,
            'input_text_tokens' => 0,
            'input_image_tokens' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
            'total_tokens' => 0,
            'cost_nanos' => 0,
        ];

        foreach ($days as $day) {
            $total['generations'] += $day['generations'] ?? 0;
            $total['input_text_tokens'] += $day['input_text_tokens'] ?? 0;
            $total['input_image_tokens'] += $day['input_image_tokens'] ?? 0;
            $total['output_tokens'] += $day['output_tokens'] ?? 0;
            $total['reasoning_tokens'] += $day['reasoning_tokens'] ?? 0;
            $total['total_tokens'] += $day['total_tokens'] ?? 0;
            $total['cost_nanos'] += $day['cost_nanos'] ?? 0;
        }

        if ($total['total_tokens'] === 0) {
            $total['total_tokens'] = $total['input_text_tokens']
                + $total['input_image_tokens']
                + $total['output_tokens']
                + $total['reasoning_tokens'];
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDay(string $date): array
    {
        return [
            'date' => $date,
            'generations' => 0,
            'input_text_tokens' => 0,
            'input_image_tokens' => 0,
            'output_tokens' => 0,
            'reasoning_tokens' => 0,
            'total_tokens' => 0,
            'cost_nanos' => 0,
        ];
    }
}
