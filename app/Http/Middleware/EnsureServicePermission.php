<?php

namespace App\Http\Middleware;

use App\Services\AuditTrail;
use App\Support\ServicePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie qu'un compte de service dispose de l'autorisation demandée.
 * Usage : ->middleware('service.can:articles.write')
 */
class EnsureServicePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()?->hasServicePermission($permission)) {
            app(AuditTrail::class)->record('api.forbidden', meta: [
                'autorisation' => $permission,
                'route'        => $request->method().' '.$request->path(),
            ], force: true);

            return response()->json([
                'message'    => 'Autorisation manquante : '.collect(explode('|', $permission))->map(ServicePermissions::label(...))->join(' ou ').'.',
                'permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
