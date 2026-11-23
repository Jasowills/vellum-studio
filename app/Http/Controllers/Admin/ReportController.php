<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GenerationCharge;
use App\Models\User;
use App\Services\Pixelforge\PixelforgeException;
use App\Services\Pixelforge\UsageReconciler;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, UsageReconciler $reconciler): Response
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:92'],
        ]);

        $days = (int) ($validated['days'] ?? 14);
        $end = CarbonImmutable::now('UTC')->startOfDay();
        $start = $end->subDays($days - 1);

        $report = null;
        $error = null;

        try {
            $report = $reconciler->compare($start, $end);
        } catch (PixelforgeException $exception) {
            $error = $exception->errorCode === 'invalid_api_key'
                ? 'Pixelforge rejected the configured API key. Usage reconciliation is unavailable.'
                : 'Pixelforge usage could not be fetched right now: '.$exception->getMessage();
        }

        $lifetime = DB::table('generation_charges')
            ->selectRaw('COUNT(*) as charged_generations')
            ->selectRaw('COALESCE(SUM(provider_cost_nanos), 0) as provider_cost_nanos')
            ->selectRaw('COALESCE(SUM(customer_charge_nanos), 0) as customer_charge_nanos')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as tokens')
            ->first();

        $recent = GenerationCharge::query()
            ->with(['generation:id,public_id,prompt,completed_at', 'user:id,name,email'])
            ->latest('settled_at')
            ->limit(15)
            ->get()
            ->map(fn (GenerationCharge $charge) => [
                'id' => $charge->generation?->public_id,
                'prompt' => $charge->generation?->prompt,
                'user' => $charge->user?->email,
                'provider_cost' => Money::formatExact($charge->provider_cost_nanos),
                'customer_charge' => Money::formatExact($charge->customer_charge_nanos),
                'margin' => Money::formatExact($charge->customer_charge_nanos - $charge->provider_cost_nanos),
                'tokens' => $charge->total_tokens,
                'settled_at' => $charge->settled_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Report', [
            'report' => $report,
            'error' => $error,
            'range' => [
                'days' => $days,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'lifetime' => [
                'charged_generations' => (int) ($lifetime->charged_generations ?? 0),
                'provider_cost' => Money::formatExact((int) ($lifetime->provider_cost_nanos ?? 0)),
                'customer_charge' => Money::formatExact((int) ($lifetime->customer_charge_nanos ?? 0)),
                'margin' => Money::formatExact((int) ($lifetime->customer_charge_nanos ?? 0) - (int) ($lifetime->provider_cost_nanos ?? 0)),
                'tokens' => (int) ($lifetime->tokens ?? 0),
                'users' => User::query()->count(),
            ],
            'recent' => $recent,
        ]);
    }
}
