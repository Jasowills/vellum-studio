<?php

namespace App\Services\Generations;

use App\Models\Generation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageArchiver
{
    /**
     * Pixelforge image URLs expire 24 hours after completion. We keep a local
     * copy so a user's library does not rot. The URL host must be on the
     * configured allowlist and redirects are never followed, which prevents
     * this worker from being used to fetch arbitrary internal resources.
     */
    public function archive(Generation $generation): bool
    {
        if ($generation->archived_image_path !== null) {
            return true;
        }

        if ($generation->image_url === null) {
            return false;
        }

        if ($generation->image_url_expires_at !== null && $generation->image_url_expires_at->isPast()) {
            Log::info('Skipped archiving an expired image URL.', ['generation' => $generation->public_id]);

            return false;
        }

        if (! $this->isAllowedUrl($generation->image_url)) {
            Log::warning('Refused to archive an image from an untrusted host.', ['generation' => $generation->public_id]);

            return false;
        }

        try {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withOptions(['allow_redirects' => false])
                ->get($generation->image_url);
        } catch (ConnectionException) {
            throw new RuntimeException('Image download failed.');
        }

        if (! $response->successful()) {
            if (in_array($response->status(), [403, 404, 410], true)) {
                Log::info('Image URL is no longer available.', [
                    'generation' => $generation->public_id,
                    'status' => $response->status(),
                ]);

                return false;
            }

            throw new RuntimeException('Image download returned '.$response->status().'.');
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if (! str_starts_with($contentType, 'image/')) {
            Log::warning('Refused to archive a non-image response.', ['generation' => $generation->public_id]);

            return false;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > 25 * 1024 * 1024) {
            Log::warning('Refused to archive an empty or oversized image.', ['generation' => $generation->public_id]);

            return false;
        }

        $path = 'generation-images/'.$generation->public_id.'.png';
        Storage::disk('local')->put($path, $body);
        $generation->forceFill(['archived_image_path' => $path])->save();

        return true;
    }

    public function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        return in_array(strtolower($parts['host']), array_map('strtolower', config('pixelforge.image_hosts', [])), true);
    }
}
