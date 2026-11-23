<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Services\Generations\ImageArchiver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerationImageController extends Controller
{
    public function __invoke(Generation $generation, ImageArchiver $archiver): StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $generation);

        if ($generation->archived_image_path !== null
            && Storage::disk('local')->exists($generation->archived_image_path)) {
            return Storage::disk('local')->response(
                $generation->archived_image_path,
                $generation->public_id.'.png',
                [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'private, max-age=86400',
                    'X-Content-Type-Options' => 'nosniff',
                ],
            );
        }

        if ($generation->image_url !== null
            && ($generation->image_url_expires_at === null || $generation->image_url_expires_at->isFuture())
            && $archiver->isAllowedUrl($generation->image_url)) {
            return redirect()->away($generation->image_url);
        }

        abort(404, 'This image is no longer available.');
    }
}
