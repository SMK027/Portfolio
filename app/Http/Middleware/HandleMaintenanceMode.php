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
        'login', 'logout', 'forgot-password', 'reset-password', 'reset-password/*',
        'admin', 'admin/*', 'robots.txt',
    ];

    public function __construct(protected Maintenance $maintenance)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()
            || $request->is(...self::ALLOWED_PATHS)
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
