<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RemoteImageFetcher;
use App\Services\ImageDownloadException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Point « byUrl » de l'outil image d'Editor.js : appelé lorsqu'on colle un
 * contenu contenant des images (<img src>, image intégrée en base64, lien
 * direct vers une image). Sans lui, les images collées étaient perdues.
 */
class EditorImageUrlController extends Controller
{
    public function __invoke(Request $request, RemoteImageFetcher $fetcher): JsonResponse
    {
        $request->validate(['url' => ['required', 'string', 'max:15000000']]);
        $url = trim((string) $request->input('url'));

        // Image déjà hébergée sur ce site : on conserve simplement son adresse.
        if ($local = $this->localPath($request, $url)) {
            return $this->success($local);
        }

        try {
            $stored = $fetcher->fetch($url, 'editor/'.now()->format('Y/m'));
            app(\App\Services\AuditTrail::class)->record('editor.image_uploaded', meta: [
                'fichier' => $stored, 'source' => str_starts_with($url, 'data:') ? 'image collée (base64)' : mb_substr($url, 0, 300),
            ]);

            return $this->success($stored);
        } catch (Throwable $e) {
            Log::warning('Import d\'image collée impossible ('.mb_substr($url, 0, 200).') : '.$e->getMessage());

            // Téléchargement refusé par le site d'origine (protection anti-hotlink, erreur
            // temporaire…) : mieux vaut garder un lien vers l'image que la perdre — en HTTPS
            // uniquement. Jamais pour une adresse interne ni pour un contenu qui n'est pas une image.
            if ($e instanceof ImageDownloadException
                && str_starts_with(strtolower($url), 'https://') && filter_var($url, FILTER_VALIDATE_URL)) {
                return $this->success($url);
            }

            return response()->json(['success' => 0, 'message' => 'Image non importée : '.$e->getMessage()], 422);
        }
    }

    protected function localPath(Request $request, string $url): ?string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $parts = parse_url($url);
        if (in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && strtolower($parts['host'] ?? '') === strtolower($request->getHost())) {
            return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return null;
    }

    protected function success(string $url): JsonResponse
    {
        return response()->json(['success' => 1, 'file' => ['url' => $url]]);
    }
}
