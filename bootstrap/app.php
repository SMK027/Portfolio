<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'page'  => \App\Http\Middleware\EnsurePageIsAccessible::class,
        ]);

        // Global (et non "web") pour couvrir aussi les erreurs 404 hors route.
        $middleware->append(\App\Http\Middleware\AddRobotsHeader::class);

        // Après la session et l'authentification, pour laisser passer les administrateurs.
        $middleware->web(append: [
            \App\Http\Middleware\HandleMaintenanceMode::class,
        ]);

        // Traefik termine le HTTPS : on fait confiance à ses en-têtes X-Forwarded-*
        // pour générer des URL en https://.
        // Toutes les adresses sont acceptées car mod_remoteip (Apache) remplace déjà
        // l'IP du proxy par celle du visiteur avant Laravel : une liste d'IP privées
        // ne correspondrait jamais. C'est sûr ici : le conteneur n'est joignable que
        // via Traefik (qui écrase les X-Forwarded-* reçus des visiteurs) ou depuis
        // l'hôte. X-Forwarded-Host est ignoré : le domaine vient de l'en-tête Host,
        // garanti par la règle Host() du routeur Traefik.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
