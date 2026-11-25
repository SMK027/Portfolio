<?php

namespace App\Services;

use App\Models\Supervisor;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Bypass superviseur des erreurs 429 (limitation de débit « throttle:X,Y »).
 *
 * La requête bloquée est mise en attente (session) ; un superviseur habilité
 * (OPERATION) la valide par identifiant + PIN ; elle est alors rejouée une fois,
 * avec un jeton à usage unique lié à la session, à la méthode et à l'adresse.
 */
class ThrottleSupervision
{
    public const OPERATION = 'throttle.bypass';

    public const PENDING_KEY = 'throttle_supervision.pending';

    public const BYPASS_FIELD = '_throttle_bypass';

    protected const NONCE_KEY = 'throttle_supervision.nonce';

    /** Limites protégeant l'authentification : jamais contournables. */
    protected const EXCLUDED_ROUTES = ['login*', 'two-factor*', 'account.setup*', 'password.*', 'verification.*', 'throttle-supervision.*'];

    /** La requête bloquée peut-elle être proposée à un superviseur ? */
    public function offerable(Request $request): bool
    {
        return $request->hasSession() && ! $request->expectsJson() && ! $request->ajax()
            && $request->route() && ! $request->routeIs(...self::EXCLUDED_ROUTES)
            && $this->anySupervisor();
    }

    public function anySupervisor(): bool
    {
        return Supervisor::with('user')->where('is_active', true)->get()
            ->contains(fn (Supervisor $s) => $s->isUsable() && $s->grantable([self::OPERATION]));
    }

    /** Met la requête bloquée en attente de validation (15 minutes). */
    public function hold(Request $request): void
    {
        $request->session()->put(self::PENDING_KEY, [
            'method'  => $request->method(),
            'url'     => $request->fullUrlWithoutQuery([self::BYPASS_FIELD]),
            'path'    => $request->path(),
            'route'   => $request->route()->getName(),
            'referer' => $request->headers->get('referer'),
            // Les fichiers envoyés ne sont pas conservés : ils seront à rejoindre.
            'input'   => $request->isMethod('GET') ? [] : Arr::except($request->input(), ['_token', '_method', self::BYPASS_FIELD]),
            'expires' => now()->addMinutes(15)->getTimestamp(),
        ]);
    }

    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        if (! $pending || $pending['expires'] < now()->getTimestamp()) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        return $pending;
    }

    /** Validation accordée : jeton à usage unique (2 minutes) pour rejouer la requête. */
    public function grant(Request $request, array $pending, Supervisor $supervisor): string
    {
        $token = Str::random(48);
        // Lien avec la session du visiteur (nonce, robuste à une régénération de l'identifiant).
        $nonce = Str::random(32);
        $request->session()->put(self::NONCE_KEY, $nonce);
        Cache::put('throttle_supervision.bypass.'.$token, [
            'nonce'      => $nonce,
            'method'     => $pending['method'],
            'path'       => $pending['path'],
            'referer'    => $pending['referer'],
            'supervisor' => $supervisor->id,
        ], now()->addMinutes(2));
        $request->session()->forget(self::PENDING_KEY);

        return $token;
    }

    /** Consomme le jeton joint à la requête : true si la limite doit être ignorée. */
    public function consume(Request $request): bool
    {
        $token = $request->query(self::BYPASS_FIELD) ?? $request->request->get(self::BYPASS_FIELD);
        if (! is_string($token) || $token === '' || ! $request->hasSession()) {
            return false;
        }
        $request->query->remove(self::BYPASS_FIELD);
        $request->request->remove(self::BYPASS_FIELD);

        $bypass = Cache::pull('throttle_supervision.bypass.'.$token);
        $nonce = $request->session()->pull(self::NONCE_KEY);
        $valid = $bypass && is_string($nonce) && hash_equals($bypass['nonce'], $nonce)
            && $bypass['method'] === $request->method() && $bypass['path'] === $request->path();

        app(AuditTrail::class)->record($valid ? 'supervision.used' : 'supervision.rejected', $bypass ? Supervisor::find($bypass['supervisor']) : null, meta: array_filter([
            'opérations' => $valid ? [self::OPERATION] : null,
            'raison'     => $valid ? null : (! $bypass ? 'jeton inconnu, expiré ou déjà utilisé' : 'jeton présenté pour une autre requête ou une autre session'),
            'requête'    => $request->method().' /'.$request->path(),
        ]), force: true);

        if ($valid && $bypass['referer']) {
            $request->headers->set('referer', $bypass['referer']); // back() après une erreur de validation
        }

        return $valid;
    }
}
