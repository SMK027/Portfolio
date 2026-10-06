<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité sur toutes les réponses (pages, API, fichiers).
 *
 * La CSP n'autorise que les origines utilisées par le site : polices (Bunny Fonts),
 * reCAPTCHA (Google) et vidéos intégrées aux articles (YouTube, Vimeo, CodePen, Twitch).
 * 'unsafe-inline' et 'unsafe-eval' restent nécessaires (Alpine.js évalue ses attributs,
 * gestionnaires onsubmit des boutons de suppression) ; la CSP bloque néanmoins tout script,
 * cadre ou formulaire vers une origine inconnue, ainsi que l'affichage du site dans un cadre tiers.
 * Les en-têtes déjà définis par une réponse (ex. : CSP « sandbox » des pièces jointes) sont conservés.
 */
class AddSecurityHeaders
{
    protected const CSP = [
        'default-src'     => "'self'",
        'script-src'      => "'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com",
        'style-src'       => "'self' 'unsafe-inline' https://fonts.bunny.net",
        'font-src'        => "'self' data: https://fonts.bunny.net",
        'img-src'         => "'self' data: blob: https:",
        'connect-src'     => "'self' https://www.google.com",
        'frame-src'       => "'self' https://www.google.com https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://codepen.io https://player.twitch.tv",
        'object-src'      => "'none'",
        'base-uri'        => "'self'",
        'form-action'     => "'self'",
        'frame-ancestors' => "'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('app.security_headers', true)) {
            return $response;
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => collect(self::CSP)->map(fn ($value, $directive) => "{$directive} {$value}")->implode('; '),
        ];

        // HTTPS uniquement (derrière Traefik, l'en-tête X-Forwarded-Proto est pris en compte).
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
