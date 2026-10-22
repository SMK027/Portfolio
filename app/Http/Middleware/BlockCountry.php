<?php

namespace App\Http\Middleware;

use App\Services\CountryBlock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse les requêtes (site, panel, API) provenant d'un pays de la liste noire
 * (voir App\Services\CountryBlock). Middleware global, avant la session :
 * réponse 403 sans cookie. La route de santé /up reste accessible.
 */
class BlockCountry
{
    public function __construct(protected CountryBlock $block)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up') || ! ($country = $this->block->blockedCountryFor($request))) {
            return $next($request);
        }

        $message = 'L\'accès à ce site n\'est pas disponible depuis votre pays.';
        $headers = ['Cache-Control' => 'no-store, private'];

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $message], 403, $headers);
        }

        return response()->view('errors.country-blocked', ['message' => $message], 403, $headers);
    }
}
