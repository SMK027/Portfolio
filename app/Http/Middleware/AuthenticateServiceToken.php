<?php

namespace App\Http\Middleware;

use App\Models\ServiceToken;
use App\Services\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentification de l'API par code d'application (Authorization: Bearer pfs_…).
 */
class AuthenticateServiceToken
{
    public function __construct(protected AuditTrail $audit)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        $token = $plain !== '' ? ServiceToken::findValid($plain) : null;
        $account = $token?->user;

        if (! $token || ! $account || ! $account->isService() || ! $account->is_active) {
            $this->audit->record('api.auth_failed', $account, meta: [
                'raison' => match (true) {
                    $plain === ''          => 'code absent',
                    ! $token               => 'code inconnu ou désactivé',
                    default                => 'compte désactivé ou non autorisé à l\'API',
                },
                'préfixe' => $plain !== '' ? substr($plain, 0, 12) : null,
                'route'   => $request->method().' '.$request->path(),
            ], force: true);

            return response()->json(['message' => 'Code d\'application invalide, désactivé ou compte désactivé.'], 401);
        }

        // Dernière utilisation (au plus une écriture par minute).
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }

        Auth::setUser($account);
        $this->audit->actingThroughApi($account);
        $request->attributes->set('service_token', $token);

        return $next($request);
    }
}
