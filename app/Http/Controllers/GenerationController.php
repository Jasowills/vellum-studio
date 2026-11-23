<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Services\Generations\CreateGeneration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function store(StoreGenerationRequest $request, CreateGeneration $create): RedirectResponse
    {
        $result = $create->handle($request->user(), $request->validated());

        return redirect()
            ->route('generations.show', $result['generation'])
            ->with('status', $result['created'] ? 'Your image is on its way.' : 'Resumed your earlier attempt.');
    }

    public function show(Generation $generation): Response
    {
        Gate::authorize('view', $generation);

        $generation->load('charge');

        return Inertia::render('Generation', [
            'generation' => (new GenerationResource($generation))->resolve(),
        ]);
    }
}
