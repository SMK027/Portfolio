<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * « throttle:X,Y » avec un compteur propre à chaque route.
 *
 * Par défaut, Laravel compte toutes les routes limitées sur une seule clé
 * (domaine + IP, ou utilisateur) : les requêtes d'arrière-plan (durée de lecture,
 * succès, file d'attente…) épuisaient la limite du formulaire de contact (5 / 10 min)
 * et provoquaient des erreurs 429 sans raison apparente.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();

        return sha1(($route?->getName() ?? $route?->uri() ?? '').'|'.parent::resolveRequestSignature($request));
    }
}
