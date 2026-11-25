<?php

namespace App\Http\Middleware;

use App\Services\ThrottleSupervision;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * « throttle:X,Y » avec un compteur propre à chaque route, et bypass superviseur.
 *
 * Par défaut, Laravel compte toutes les routes limitées sur une seule clé
 * (domaine + IP, ou utilisateur) : les requêtes d'arrière-plan (durée de lecture,
 * succès…) épuisaient la limite du formulaire de contact (erreurs 429 injustifiées).
 *
 * Limite atteinte sur une page : un superviseur habilité peut débloquer la requête
 * (App\Services\ThrottleSupervision), rejouée une fois sans être comptée.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        $supervision = app(ThrottleSupervision::class);
        if ($supervision->consume($request)) {
            return $next($request);
        }

        try {
            return parent::handle($request, $next, ...array_slice(func_get_args(), 2));
        } catch (ThrottleRequestsException $e) {
            if (! $supervision->offerable($request)) {
                throw $e;
            }
            $supervision->hold($request);

            return response()->view('errors.429', ['supervision' => true], 429, $e->getHeaders());
        }
    }

    protected function resolveRequestSignature($request)
    {
        $route = $request->route();

        return sha1(($route?->getName() ?? $route?->uri() ?? '').'|'.parent::resolveRequestSignature($request));
    }
}
