<?php

namespace App\Http\Middleware;

use App\Services\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coupe immédiatement la session d'un compte humain désactivé, ou arrivé à sa date
 * de désactivation programmée (personnel). Les bots sont traités par EnsureBotTokenIsValid.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isMachine() || $user->isActive()) {
            return $next($request);
        }

        app(AuditTrail::class)->record('auth.session_revoked', $user, meta: [
            'raison' => $user->is_active ? 'désactivation programmée atteinte' : 'compte désactivé',
        ], force: true);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['message' => 'Compte désactivé.'], 401)
            : redirect()->route('login')->withErrors(['email' => 'Ce compte est désactivé.']);
    }
}
