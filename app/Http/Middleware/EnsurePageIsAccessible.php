<?php

namespace App\Http\Middleware;

use App\Models\Page;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'accès à une page privée pour les visiteurs non administrateurs.
 * Une page privée répond 404 afin de ne pas révéler son existence.
 *
 * Usage : ->middleware('page:projets')
 */
class EnsurePageIsAccessible
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        $page = Page::findByKey($key);

        if ($page?->isAccessibleBy($request->user())) {
            $request->attributes->set('page', $page);

            return $next($request);
        }

        // Accueil privé : on redirige vers la première page accessible.
        if ($key === 'home') {
            $fallback = Page::visibleTo($request->user())->first();

            if ($fallback) {
                return redirect($fallback->url());
            }
        }

        abort(404);
    }
}
