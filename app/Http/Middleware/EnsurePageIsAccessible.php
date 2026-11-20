<?php

namespace App\Http\Middleware;

use App\Models\Page;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique le statut d'une page aux visiteurs non administrateurs : brouillon ou
 * privée → 404 (existence non révélée), maintenance → 503, mot de passe → formulaire.
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

        // Maintenance : page d'attente (503) ; mot de passe : formulaire de déverrouillage
        if ($page?->status === Page::MAINTENANCE) {
            return response()->view('public.page-maintenance', ['page' => $page], 503)->header('Retry-After', 3600);
        }
        if ($page?->status === Page::PASSWORD && $page->password) {
            abort_unless($request->isMethod('GET'), 403);

            return response()->view('public.page-locked', ['page' => $page]);
        }

        // Accueil brouillon ou privé : on redirige vers la première page accessible.
        if ($key === 'home') {
            $fallback = Page::visibleTo($request->user())->first();

            if ($fallback) {
                return redirect($fallback->url());
            }
        }

        abort(404);
    }
}
