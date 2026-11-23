<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Http\Resources\GenerationResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_column(GenerationStatus::cases(), 'value'))],
            'quality' => ['nullable', Rule::in(config('pixelforge.qualities'))],
            'size' => ['nullable', Rule::in(config('pixelforge.sizes'))],
        ]);

        $generations = $request->user()->generations()
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where('prompt', 'like', '%'.$q.'%'))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['quality'] ?? null, fn ($query, $quality) => $query->where('quality', $quality))
            ->when($filters['size'] ?? null, fn ($query, $size) => $query->where('size', $size))
            ->latest()
            ->paginate(24)
            ->withQueryString()
            ->through(fn ($generation) => (new GenerationResource($generation))->resolve());

        return Inertia::render('Library', [
            'generations' => $generations,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'quality' => $filters['quality'] ?? '',
                'size' => $filters['size'] ?? '',
            ],
            'statuses' => array_map(fn (GenerationStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ], GenerationStatus::cases()),
            'qualities' => config('pixelforge.qualities'),
            'sizes' => config('pixelforge.sizes'),
        ]);
    }
}
