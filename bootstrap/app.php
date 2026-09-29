<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
