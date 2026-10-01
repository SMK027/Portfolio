<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Codes à usage unique basés sur le temps (RFC 6238 : SHA-1, 6 chiffres,
 * 30 secondes), compatibles Google Authenticator, Authy, 1Password…
 */
final class Totp
{
    public const PERIOD = 30;

    public const DIGITS = 6;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Nouveau secret (160 bits) encodé en base32. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** Code attendu pour un pas de temps donné. */
    public static function code(string $secret, int $step, int $digits = self::DIGITS): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Pas de temps correspondant au code (± une période de tolérance), ou null.
     * Les pas déjà utilisés ($after) sont refusés : un code ne sert qu'une fois.
     */
    public static function verify(string $secret, string $code, ?int $after = null, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $current = intdiv($time ?? time(), self::PERIOD);
        foreach ([$current, $current - 1, $current + 1] as $step) {
            if (($after === null || $step > $after) && hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function uri(string $issuer, string $account, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account)
            .'?'.http_build_query(['secret' => $secret, 'issuer' => $issuer, 'digits' => self::DIGITS, 'period' => self::PERIOD], '', '&', PHP_QUERY_RFC3986);
    }

    /** QR code SVG à scanner avec l'application. */
    public static function qrSvg(string $uri): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($uri);

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    /** Secret affiché par groupes de 4 pour la saisie manuelle. */
    public static function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn ($chunk) => self::BASE32[bindec(str_pad($chunk, 5, '0'))], str_split($bits, 5)));
    }

    public static function base32Decode(string $text): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[\s=]/', '', $text))) as $char) {
            $index = strpos(self::BASE32, $char);
            if ($index === false) {
                throw new \InvalidArgumentException('Secret base32 invalide.');
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn ($byte) => chr(bindec($byte)), array_filter(str_split($bits, 8), fn ($b) => strlen($b) === 8)));
    }
}
