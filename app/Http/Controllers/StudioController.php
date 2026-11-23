<?php

namespace App\Http\Controllers;

use App\Http\Resources\GenerationResource;
use App\Services\Pixelforge\PricingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class StudioController extends Controller
{
    public function __invoke(Request $request, PricingService $pricing): Response
    {
        $user = $request->user();
        $source = null;

        if ($request->filled('from')) {
            $source = $user->generations()
                ->where('public_id', (string) $request->query('from'))
                ->first();
        }

        $pricingError = null;

        try {
            $pricingPayload = $pricing->snapshot();
        } catch (Throwable $exception) {
            report($exception);
            $pricingPayload = null;
            $pricingError = 'Pricing is temporarily unavailable. You can compose a prompt, but generation is paused until Pixelforge responds.';
        }

        $recent = $user->generations()->latest()->limit(6)->get();

        return Inertia::render('Studio', [
            'pricing' => $pricingPayload,
            'pricingError' => $pricingError,
            'sizes' => config('pixelforge.sizes'),
            'qualities' => config('pixelforge.qualities'),
            'defaultSize' => config('pixelforge.default_size'),
            'defaultQuality' => config('pixelforge.default_quality'),
            'maxReferenceImages' => config('pixelforge.max_reference_images'),
            'maxPromptCharacters' => config('pixelforge.max_prompt_characters'),
            'recent' => $recent->map(fn ($generation) => (new GenerationResource($generation))->resolve())->all(),
            'prefill' => $source === null ? null : [
                'prompt' => $source->prompt,
                'size' => $source->size,
                'quality' => $source->quality,
                'reference_images' => $source->reference_images ?? [],
                'source_id' => $source->public_id,
            ],
        ]);
    }
}
