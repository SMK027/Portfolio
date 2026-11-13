<?php

namespace App\Http\Middleware;

use App\Models\Profile;
use App\Services\WaitingRoom;
use App\Support\UserAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Met les visiteurs en file d'attente en cas de forte affluence (voir App\Services\WaitingRoom).
 * Comme la maintenance, la page d'attente est renvoyée avec un statut 200.
 */
class HandleWaitingRoom
{
    /** Toujours accessibles : connexion, fichiers techniques et suivi de la file. */
    protected const ALLOWED_PATHS = [
        'double-authentification', 'double-authentification/*', 'logout', 'forgot-password', 'reset-password', 'reset-password/*',
        'admin', 'admin/*', 'dashboard', 'profile', 'profile/*', 'supervision', 'robots.txt', 'sitemap.xml', 'up',
        'file-attente/*', 'rendez-vous/annuler/*',
    ];

    public function __construct(protected WaitingRoom $room)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()
            || $request->is(...self::ALLOWED_PATHS)
            || $request->routeIs('login', 'login.*')
            || ! $this->room->isEnabled()) {
            return $next($request);
        }

        $ticket = $this->room->find($request->cookie(WaitingRoom::COOKIE));

        if ($ticket?->isAdmitted()) {
            $this->room->touch($ticket);

            return $this->measured($request, $next);
        }

        // Robots : jamais de ticket (ils ne gardent pas les cookies et occuperaient des places).
        $isBot = UserAgent::isBot($request->userAgent());

        if (! $ticket && ! $this->room->isQueueing()) {
            if ($isBot) {
                return $next($request);
            }

            $token = $this->room->issue(admitted: true)->token;

            return $this->withTicket($this->measured($request, $next), $token);
        }

        if (! $ticket && ! $isBot) {
            $ticket = $this->room->issue(admitted: false);
        }

        $this->room->markTriggered();

        if ($ticket) {
            $this->room->touch($ticket);
            $this->room->advance();

            if ($ticket->refresh()->isAdmitted()) {
                return $this->withTicket($next($request), $ticket->token);
            }
        }

        $position = $ticket ? $this->room->position($ticket) : $this->room->waitingCount() + 1;

        $response = response()
            ->view('waiting-room', [
                'profile'  => Profile::current(),
                'message'  => $this->room->message(),
                'position' => $position,
                'minutes'  => $this->room->estimatedMinutes($position),
            ], 200)
            ->header('Cache-Control', 'no-store, private')
            ->header('Retry-After', '10');

        return $ticket ? $this->withTicket($response, $ticket->token) : $response;
    }

    /** Réponse d'un visiteur admis, dont la durée alimente le déclenchement selon la charge. */
    protected function measured(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $this->room->recordResponseTime((microtime(true) - $start) * 1000);

        return $response;
    }

    protected function withTicket(Response $response, string $token): Response
    {
        $response->headers->setCookie(cookie(WaitingRoom::COOKIE, $token, 60 * 24, httpOnly: true, sameSite: 'lax'));

        return $response;
    }
}
