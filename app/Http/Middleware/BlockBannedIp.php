<?php

namespace App\Http\Middleware;

use App\Services\LoginBan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse toutes les requêtes (site, panel, API) d'une adresse IP bannie, après
 * des échecs de connexion répétés ou manuellement (voir App\Services\LoginBan).
 * Middleware global, exécuté avant la session : réponse sans cookie, avec le
 * code HTTP choisi pour ce bannissement (403 par défaut).
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

        $message = $ban->publicMessage();
        $status = $ban->status();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message'      => $message,
                'banned_until' => $ban->banned_until?->toIso8601String(),
            ], $status, $headers);
        }

        return response()->view('errors.banned', ['ban' => $ban, 'message' => $message, 'status' => $status], $status, $headers);
    }
}
