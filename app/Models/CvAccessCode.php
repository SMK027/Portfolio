<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Code d'accès au CV, remis à un destinataire (candidature, recruteur…) :
 * sans code valide, le CV n'est pas servi, ce qui écarte robots et aspirateurs.
 */
#[Fillable(['label', 'code', 'expires_at', 'max_uses'])]
class CvAccessCode extends Model
{
    use Auditable;

    /** Alphabet sans caractères ambigus (0/O, 1/I/L). */
    protected const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected const LENGTH = 8;

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'last_used_at' => 'datetime',
            'max_uses'     => 'integer',
            'uses'         => 'integer',
        ];
    }

    public static function generate(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Saisie ramenée au format stocké : majuscules, sans espaces ni tirets. */
    public static function normalize(?string $input): string
    {
        return substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $input)), 0, 16);
    }

    /** Code valide correspondant à la saisie, ou null. */
    public static function findUsable(?string $input): ?self
    {
        $code = self::normalize($input);

        return $code === '' ? null : static::where('code', $code)->get()->first(fn (self $c) => $c->isUsable());
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->uses >= $this->max_uses;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isExhausted();
    }

    /** Comptabilise une utilisation, sans passer par l'historique des modifications. */
    public function markUsed(): void
    {
        static::whereKey($this->id)->increment('uses', 1, ['last_used_at' => now()]);
    }

    /** Code affiché par groupes de 4 (ABCD-EFGH). */
    public function formatted(): string
    {
        return implode('-', str_split($this->code, 4));
    }

    /** Lien à transmettre : ouvre le formulaire avec le code prérempli. */
    public function shareUrl(): string
    {
        return route('cv.download', ['code' => $this->formatted()]);
    }
}
