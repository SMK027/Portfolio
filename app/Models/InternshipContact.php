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

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Relance due (date atteinte) alors que la réponse est toujours attendue. */
    public function followUpDue(): bool
    {
        return $this->isAwaiting() && $this->follow_up_on?->lte(today());
    }
}
