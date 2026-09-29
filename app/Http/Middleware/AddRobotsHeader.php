<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Ajoute l'en-tête "X-Robots-Tag: noindex, nofollow" :
 * - sur toutes les réponses quand l'indexation du site est désactivée
 *   (couvre aussi les fichiers servis : PDF, documents, images de projets) ;
 * - toujours sur l'administration et les pages de connexion.
 */
class AddRobotsHeader
{
    protected const PRIVATE_PATHS = ['admin', 'admin/*', 'login', 'forgot-password', 'reset-password/*', 'profile', 'dashboard', 'verify-email*', 'confirm-password'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is(...self::PRIVATE_PATHS) || ! $this->siteIsIndexable()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    protected function siteIsIndexable(): bool
    {
        try {
            return Setting::siteIsIndexable();
        } catch (Throwable) {
            // Base indisponible (installation, maintenance) : on reste prudent.
            return false;
        }
    }
}
