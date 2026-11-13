<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Entreprise ou organisme contacté pendant la recherche de stage. */
#[Fillable(['company', 'address', 'contact_name', 'contacted_on', 'contact_method', 'status', 'followed_up_on', 'follow_up_on', 'notes'])]
class InternshipContact extends Model
{
    use Auditable;

    public const METHODS = [
        'email'     => 'E-mail',
        'call'      => 'Appel',
        'letter'    => 'Lettre',
        'in_person' => 'Présentiel',
        'web_form'  => 'Formulaire web',
        'other'     => 'Autre',
    ];

    public const STATUSES = [
        'pending'    => 'En attente de réponse',
        'processing' => 'En cours de traitement',
        'accepted'   => 'Accepté',
        'refused'    => 'Refusé',
        'no_response' => 'Sans réponse',
    ];

    /** Libellés courts (badges du panel). */
    public const STATUS_SHORT = [
        'pending'    => 'En attente',
        'processing' => 'En cours',
        'accepted'   => 'Accepté',
        'refused'    => 'Refusé',
        'no_response' => 'Sans réponse',
    ];

    /** Couleurs de chaque résultat : classe de badge (panel), fond et texte (exports). */
    public const STATUS_STYLES = [
        'pending'    => ['badge-amber', 'FEF3C7', '92400E'],
        'processing' => ['badge-primary', 'E0E7FF', '3730A3'],
        'accepted'   => ['badge-green', 'D1FAE5', '065F46'],
        'refused'    => ['badge-red', 'FEE2E2', '991B1B'],
        'no_response' => ['badge-slate', 'E2E8F0', '475569'],
    ];

    /** Icône de chaque moyen de contact. */
    public const METHOD_ICONS = [
        'email'     => 'mail',
        'call'      => 'phone',
        'letter'    => 'document',
        'in_person' => 'map-pin',
        'web_form'  => 'globe',
        'other'     => 'ellipsis',
    ];

    /** Résultats comptés comme un refus : « Sans réponse » vaut refus à défaut de réponse. */
    public const REFUSALS = ['refused', 'no_response'];

    /** Résultats définitifs (réponse obtenue ou considérée comme telle). */
    public const CLOSED = ['accepted', ...self::REFUSALS];

    /** Résultats pour lesquels une relance prévue a un sens. */
    public const AWAITING = ['pending', 'processing'];

    protected function casts(): array
    {
        return ['contacted_on' => 'date', 'followed_up_on' => 'date', 'follow_up_on' => 'date'];
    }

    public function auditLabel(): string
    {
        return $this->company;
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('contacted_on')->orderByDesc('id');
    }

    public function isAwaiting(): bool
    {
        return in_array($this->status, self::AWAITING, true);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->contact_method] ?? $this->contact_method;
    }

    public function statusShortLabel(): string
    {
        return self::STATUS_SHORT[$this->status] ?? $this->statusLabel();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** @return array{0: string, 1: string, 2: string} */
    public function statusStyle(): array
    {
        return self::STATUS_STYLES[$this->status] ?? ['badge-slate', 'F1F5F9', '334155'];
    }

    public function methodIcon(?string $method = null): string
    {
        return self::METHOD_ICONS[$method ?? $this->contact_method] ?? 'mail';
    }

    /** Relance prévue due (date atteinte) alors que la réponse est toujours attendue. */
    public function followUpDue(): bool
    {
        return $this->isAwaiting() && $this->follow_up_on?->lte(today());
    }

    /** Jours restants avant la relance prévue (négatif : en retard), null sans relance prévue. */
    public function followUpInDays(): ?int
    {
        return $this->isAwaiting() && $this->follow_up_on ? (int) today()->diffInDays($this->follow_up_on, false) : null;
    }

    /** Échéance de la relance prévue, lisible : « en retard de 3 j », « aujourd'hui », « dans 5 j ». */
    public function followUpHint(): ?string
    {
        return match (true) {
            ($days = $this->followUpInDays()) === null => null,
            $days < 0   => 'en retard de '.abs($days).' j',
            $days === 0 => 'aujourd\'hui',
            $days === 1 => 'demain',
            default     => "dans {$days} j",
        };
    }

    /** Mots ignorés pour comparer les noms (formes juridiques, articles). */
    protected const NAME_NOISE = ['sas', 'sasu', 'sarl', 'eurl', 'sa', 'sci', 'snc', 'scop', 'sem', 'spa', 'gie', 'societe', 'ste', 'cie',
        'groupe', 'group', 'et', 'de', 'des', 'du', 'la', 'le', 'les', 'l', 'd', 'the', 'and', 'france', 'inc', 'ltd', 'gmbh'];

    /** Nom ou adresse réduit pour comparaison : sans accents, casse, ponctuation ni mots vides. */
    public static function normalize(?string $value, bool $isName = true): string
    {
        $words = preg_split('/[^a-z0-9]+/', \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii((string) $value)), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', $isName ? array_diff($words, self::NAME_NOISE) : $words);
    }

    /**
     * Contacts déjà enregistrés semblables à celui saisi : nom d'entreprise
     * identique ou proche (fautes de frappe, forme juridique, nom inclus dans
     * l'autre), ou même adresse.
     *
     * @return \Illuminate\Support\Collection<int, array{contact: self, reasons: list<string>}>
     */
    public static function similarTo(?string $company, ?string $address = null, ?int $exceptId = null): \Illuminate\Support\Collection
    {
        $name = self::normalize($company);
        $place = self::normalize($address, isName: false);
        if (mb_strlen($name) < 2 && mb_strlen($place) < 8) {
            return collect();
        }

        return self::query()->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->orderByDesc('contacted_on')->get()
            ->map(function (self $contact) use ($name, $place) {
                $reasons = [];
                $other = self::normalize($contact->company);

                if ($name !== '' && $other !== '') {
                    $compact = str_replace(' ', '', $name);
                    $otherCompact = str_replace(' ', '', $other);
                    $tolerance = strlen($compact) >= 10 ? 2 : (strlen($compact) >= 5 ? 1 : 0);

                    if ($compact === $otherCompact) {
                        $reasons[] = 'Même entreprise';
                    } elseif (levenshtein($compact, $otherCompact) <= $tolerance) {
                        $reasons[] = 'Nom très proche';
                    } elseif (min(strlen($compact), strlen($otherCompact)) >= 4
                        && (str_contains(" {$other} ", " {$name} ") || str_contains(" {$name} ", " {$other} "))) {
                        $reasons[] = 'Nom inclus dans l\'autre';
                    }
                }

                if (mb_strlen($place) >= 8 && $place === self::normalize($contact->address, isName: false)) {
                    $reasons[] = 'Même adresse';
                }

                return $reasons ? ['contact' => $contact, 'reasons' => $reasons] : null;
            })
            ->filter()->take(5)->values();
    }
}
