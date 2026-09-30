<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\BotLoginController;
use App\Models\ServiceToken;
use App\Services\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * À chaque requête d'un bot connecté, vérifie que le code d'application de sa
 * session est toujours actif (et le compte aussi). Sinon la session est détruite
 * immédiatement : la désactivation ou la suppression d'un code prend effet tout de suite.
 */
class EnsureBotTokenIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isMachine()) {
            return $next($request);
        }

        $token = $user->isBot()
            ? ServiceToken::with('user')->find($request->session()->get(BotLoginController::SESSION_KEY))
            : null;

        if ($token && $token->user_id === $user->id && $token->isUsable()) {
            if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
                $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
            }

            return $next($request);
        }

        // Code désactivé / supprimé, compte désactivé, ou session sans code (compte de service) : accès coupé.
        app(AuditTrail::class)->record('auth.session_revoked', $user, meta: [
            'raison' => match (true) {
                ! $user->isBot()      => 'compte de service : connexion au panel interdite',
                ! $token              => 'code supprimé',
                $token->isDisabled()  => 'code désactivé',
                default               => 'compte désactivé',
            },
        ], force: true);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['message' => 'Session révoquée.'], 401)
            : redirect()->route('login.bot')->withErrors(['code' => 'Votre accès a été révoqué (code d\'application désactivé ou supprimé).']);
    }
}
