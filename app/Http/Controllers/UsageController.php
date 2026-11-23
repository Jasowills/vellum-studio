<?php

namespace App\Http\Controllers;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $start = $today->subDays(29);

        $generationCounts = DB::table('generations')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status IN ('pending', 'queued', 'processing') THEN 1 ELSE 0 END) as active")
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('day');

        $chargeDays = DB::table('generation_charges')
            ->join('generations', 'generations.id', '=', 'generation_charges.generation_id')
            ->where('generation_charges.user_id', $user->id)
            ->where('generations.created_at', '>=', $start)
            ->selectRaw('DATE(generations.created_at) as day')
            ->selectRaw('COUNT(*) as charged')
            ->selectRaw('COALESCE(SUM(generation_charges.customer_charge_nanos), 0) as charge_nanos')
            ->selectRaw('COALESCE(SUM(generation_charges.total_tokens), 0) as tokens')
            ->groupByRaw('DATE(generations.created_at)')
            ->get()
            ->keyBy('day');

        $days = [];

        for ($date = $start; $date->lessThanOrEqualTo($today); $date = $date->addDay()) {
            $key = $date->toDateString();
            $counts = $generationCounts->get($key);
            $charges = $chargeDays->get($key);
            $chargeNanos = (int) ($charges->charge_nanos ?? 0);

            $days[] = [
                'date' => $key,
                'label' => $date->format('M j'),
                'total' => (int) ($counts->total ?? 0),
                'succeeded' => (int) ($counts->succeeded ?? 0),
                'failed' => (int) ($counts->failed ?? 0),
                'active' => (int) ($counts->active ?? 0),
                'charged' => (int) ($charges->charged ?? 0),
                'charge_nanos' => $chargeNanos,
                'charge' => Money::formatUsd($chargeNanos),
                'tokens' => (int) ($charges->tokens ?? 0),
            ];
        }

        $summary = DB::table('generations')
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status IN ('pending', 'queued', 'processing') THEN 1 ELSE 0 END) as active")
            ->first();

        $ledger = DB::table('generation_charges')
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) as charged')
            ->selectRaw('COALESCE(SUM(customer_charge_nanos), 0) as charge_nanos')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as tokens')
            ->first();

        $recent = DB::table('generation_charges')
            ->join('generations', 'generations.id', '=', 'generation_charges.generation_id')
            ->where('generation_charges.user_id', $user->id)
            ->orderByDesc('generation_charges.settled_at')
            ->limit(12)
            ->get([
                'generations.public_id',
                'generations.prompt',
                'generation_charges.customer_charge_nanos',
                'generation_charges.total_tokens',
                'generation_charges.settled_at',
            ])
            ->map(fn ($row) => [
                'id' => $row->public_id,
                'prompt' => $row->prompt,
                'charge_nanos' => (int) $row->customer_charge_nanos,
                'charge' => Money::formatUsd((int) $row->customer_charge_nanos),
                'tokens' => (int) $row->total_tokens,
                'settled_at' => $row->settled_at,
            ]);

        $chargeNanos = (int) ($ledger->charge_nanos ?? 0);

        return Inertia::render('Usage', [
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'succeeded' => (int) ($summary->succeeded ?? 0),
                'failed' => (int) ($summary->failed ?? 0),
                'active' => (int) ($summary->active ?? 0),
                'charged' => (int) ($ledger->charged ?? 0),
                'charge_nanos' => $chargeNanos,
                'charge' => Money::formatUsd($chargeNanos),
                'tokens' => (int) ($ledger->tokens ?? 0),
            ],
            'days' => $days,
            'recent' => $recent,
            'range' => [
                'start' => $start->toDateString(),
                'end' => $today->toDateString(),
            ],
        ]);
    }
}
