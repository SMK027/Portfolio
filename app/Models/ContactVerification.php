<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Code à 6 chiffres envoyé à l'adresse saisie dans le formulaire de contact : le message
 * n'est enregistré qu'une fois ce code confirmé. Chaque code envoyé compte dans la limite
 * d'envois (MAX_SENDS sur BLOCK_HOURS heures, par adresse IP et par adresse e-mail).
 */
#[Fillable(['email', 'ip_address', 'code_hash', 'payload', 'recaptcha_score', 'attempts', 'expires_at', 'verified_at', 'contact_message_id'])]
class ContactVerification extends Model
{
    /** Envois de code autorisés avant blocage. */
    public const MAX_SENDS = 3;

    /** Durée du blocage, à partir du dernier envoi. */
    public const BLOCK_HOURS = 12;

    /** Validité d'un code, en minutes. */
    public const TTL_MINUTES = 15;

    /** Essais de saisie autorisés par code. */
    public const MAX_ATTEMPTS = 5;

    protected $hidden = ['code_hash', 'payload'];

    protected function casts(): array
    {
        return [
            'payload'         => 'encrypted:array',
            'recaptcha_score' => 'float',
            'expires_at'      => 'datetime',
            'verified_at'     => 'datetime',
        ];
    }

    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /**
     * Fin du blocage si MAX_SENDS codes ont déjà été envoyés à cette adresse IP ou e-mail
     * (en moins de BLOCK_HOURS heures) et que le dernier date de moins de BLOCK_HOURS heures.
     */
    public static function blockedUntil(?string $ip, ?string $email): ?Carbon
    {
        $window = now()->subHours(self::BLOCK_HOURS * 2);
        $latest = null;

        foreach (array_filter(['ip_address' => $ip, 'email' => $email]) as $column => $value) {
            $sends = static::query()->where($column, $value)->where('created_at', '>', $window)
                ->latest('created_at')->latest('id')->limit(self::MAX_SENDS)->pluck('created_at');

            if ($sends->count() < self::MAX_SENDS) {
                continue;
            }
            $last = Carbon::parse($sends->first());
            $first = Carbon::parse($sends->last());
            if ($first->gt($last->copy()->subHours(self::BLOCK_HOURS))) {
                $until = $last->copy()->addHours(self::BLOCK_HOURS);
                if ($until->isFuture() && (! $latest || $until->gt($latest))) {
                    $latest = $until;
                }
            }
        }

        return $latest;
    }

    /** Envois encore possibles pour cette adresse IP et cette adresse e-mail. */
    public static function remainingSends(?string $ip, ?string $email): int
    {
        if (static::blockedUntil($ip, $email)) {
            return 0;
        }
        $since = now()->subHours(self::BLOCK_HOURS);
        $used = 0;
        foreach (array_filter(['ip_address' => $ip, 'email' => $email]) as $column => $value) {
            $used = max($used, static::query()->where($column, $value)->where('created_at', '>', $since)->count());
        }

        return max(0, self::MAX_SENDS - $used);
    }

    public function isUsable(): bool
    {
        return ! $this->verified_at && $this->expires_at->isFuture() && $this->attempts < self::MAX_ATTEMPTS;
    }

    public function matches(string $code): bool
    {
        return hash_equals($this->code_hash, self::hashCode($code));
    }

    /** Envois de plus de 2 jours : supprimés (messages jamais confirmés, adresses IP). */
    public static function prune(): int
    {
        return static::query()->where('created_at', '<', now()->subDays(2))->delete();
    }
}
