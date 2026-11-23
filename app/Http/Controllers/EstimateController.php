<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstimateRequest;
use App\Services\Pixelforge\EstimateService;
use App\Services\Pixelforge\PixelforgeException;
use App\Services\Pixelforge\PricingService;
use Illuminate\Http\JsonResponse;

class EstimateController extends Controller
{
    public function __invoke(
        EstimateRequest $request,
        EstimateService $estimates,
        PricingService $pricing,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $estimate = $estimates->estimate(
                $pricing->current(),
                $validated['prompt'] ?? '',
                $validated['size'],
                $validated['quality'],
                $validated['reference_images'] ?? [],
            );
        } catch (PixelforgeException $exception) {
            return response()->json([
                'message' => 'Pricing is temporarily unavailable. Nothing can be generated until it is restored.',
                'code' => $exception->errorCode,
            ], 503);
        }

        return response()->json([
            'customer_charge_nanos' => $estimate['customer_charge_nanos'],
            'customer_charge' => $estimate['customer_charge'],
            'total_tokens' => $estimate['total_tokens'],
            'text_tokens' => $estimate['text_tokens'],
            'input_image_tokens' => $estimate['input_image_tokens'],
            'output_tokens' => $estimate['output_tokens'],
            'reasoning_tokens' => $estimate['reasoning_tokens'],
            'markup_percent' => $estimate['markup_percent'],
            'pricing_fetched_at' => $estimate['pricing_fetched_at'],
        ]);
    }
}
