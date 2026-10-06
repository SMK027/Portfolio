<?php

namespace App\Http\Middleware;

use App\Services\LoginBan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse toutes les requêtes (site, panel, API) d'une adresse IP bannie
 * après des échecs de connexion répétés (voir App\Services\LoginBan).
 * Middleware global, exécuté avant la session : réponse 403 sans cookie.
 */
class BlockBannedIp
{
    public function __construct(protected LoginBan $bans)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $ban = $this->bans->activeBanFor($request->ip());

        if (! $ban) {
            return $next($request);
        }

        $headers = ['Cache-Control' => 'no-store, private'];
        if ($ban->banned_until) {
            $headers['Retry-After'] = (string) max(1, (int) now()->diffInSeconds($ban->banned_until));
        }

        $message = 'Votre adresse IP est temporairement bloquée après plusieurs échecs de connexion.';

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message'      => $message,
                'banned_until' => $ban->banned_until?->toIso8601String(),
            ], 403, $headers);
        }

        return response()->view('errors.banned', ['ban' => $ban, 'message' => $message], 403, $headers);
    }
}
