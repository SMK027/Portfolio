<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Entreprise ou organisme contacté pendant la recherche de stage. */
#[Fillable(['company', 'address', 'contact_name', 'contacted_on', 'contact_method', 'status', 'follow_up_on', 'follow_up_method', 'notes'])]
class InternshipContact extends Model
{
    use Auditable;

    public const METHODS = [
        'email'     => 'E-mail',
        'call'      => 'Appel',
        'letter'    => 'Lettre',
        'in_person' => 'Présentiel',
    ];

    public const STATUSES = [
        'pending'    => 'En attente de réponse',
        'processing' => 'En cours de traitement',
        'accepted'   => 'Accepté',
        'refused'    => 'Refusé',
    ];

    /** Libellés courts (badges du panel). */
    public const STATUS_SHORT = [
        'pending'    => 'En attente',
        'processing' => 'En cours',
        'accepted'   => 'Accepté',
        'refused'    => 'Refusé',
    ];

    /** Couleurs de chaque résultat : classe de badge (panel), fond et texte (exports). */
    public const STATUS_STYLES = [
        'pending'    => ['badge-amber', 'FEF3C7', '92400E'],
        'processing' => ['badge-primary', 'E0E7FF', '3730A3'],
        'accepted'   => ['badge-green', 'D1FAE5', '065F46'],
        'refused'    => ['badge-red', 'FEE2E2', '991B1B'],
    ];

    /** Icône de chaque moyen de contact. */
    public const METHOD_ICONS = [
        'email'     => 'mail',
        'call'      => 'phone',
        'letter'    => 'document',
        'in_person' => 'map-pin',
    ];

    /** Résultats pour lesquels une relance a un sens. */
    public const AWAITING = ['pending', 'processing'];

    protected function casts(): array
    {
        return ['contacted_on' => 'date', 'follow_up_on' => 'date'];
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

    public function followUpMethodLabel(): ?string
    {
        return $this->follow_up_method ? (self::METHODS[$this->follow_up_method] ?? $this->follow_up_method) : null;
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

    /** Relance due (date atteinte) alors que la réponse est toujours attendue. */
    public function followUpDue(): bool
    {
        return $this->isAwaiting() && $this->follow_up_on?->lte(today());
    }

    /** Jours restants avant la relance (négatif : en retard), null sans relance prévue. */
    public function followUpInDays(): ?int
    {
        return $this->isAwaiting() && $this->follow_up_on ? (int) today()->diffInDays($this->follow_up_on, false) : null;
    }

    /** Échéance de relance lisible : « en retard de 3 j », « aujourd'hui », « dans 5 j ». */
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
}
