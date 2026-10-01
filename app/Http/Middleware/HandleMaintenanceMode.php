<?php

namespace App\Http\Middleware;

use App\Models\Profile;
use App\Services\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Affiche la page de maintenance aux visiteurs lorsque le mode est actif.
 * Statut 200 volontaire (voir App\Services\Maintenance).
 */
class HandleMaintenanceMode
{
    /** Toujours accessibles : connexion des administrateurs et fichiers techniques. */
    protected const ALLOWED_PATHS = [
        'login', 'login/bot', 'login/cle', 'login/cle/*', 'double-authentification', 'double-authentification/*', 'logout', 'forgot-password', 'reset-password', 'reset-password/*',
        'admin', 'admin/*', 'robots.txt', 'sitemap.xml', 'veille/apercu/*', 'rendez-vous/annuler/*',
    ];

    /**
     * Accessibles aussi aux collaborateurs (rédacteurs) : panel d'administration et
     * rédaction d'articles uniquement. Le site public leur reste masqué.
     */
    protected const WRITER_PATHS = [
        'dashboard',              // redirection après connexion
        'profile', 'profile/*',   // « Mon compte » (double authentification comprise)
        'veille/*/fichiers/*',    // pièces jointes affichées dans l'éditeur et la consultation
    ];

    public function __construct(protected Maintenance $maintenance)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isAdmin()
            || $request->is(...self::ALLOWED_PATHS)
            || (($user?->canWriteArticles() || $user?->isBot()) && $request->is(...self::WRITER_PATHS))
            // Bot autorisé : site public comme hors maintenance (les pages privées restent privées)
            || $user?->hasBotPermission('maintenance.bypass')
            || ! $this->maintenance->isActive()) {
            return $next($request);
        }

        $endsAt = $this->maintenance->endsAt();

        return response()
            ->view('maintenance', [
                'profile' => Profile::current(),
                'reason'  => $this->maintenance->reason(),
                'endsAt'  => $endsAt,
            ], 200)
            ->header('Cache-Control', 'no-store, private');
    }
}
