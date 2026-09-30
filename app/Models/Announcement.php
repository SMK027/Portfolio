<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title', 'message', 'style', 'link_url', 'link_label',
    'is_active', 'is_dismissible', 'starts_at', 'ends_at', 'position',
])]
class Announcement extends Model
{
    use Auditable;

    /** Styles disponibles : libellé et icône. */
    public const STYLES = [
        'primary' => ['Mise en avant', 'megaphone'],
        'success' => ['Recherche / disponibilité', 'badge'],
        'info'    => ['Information', 'newspaper'],
        'warning' => ['Important', 'clock'],
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'is_dismissible' => 'boolean',
            'starts_at'      => 'datetime',
            'ends_at'        => 'datetime',
        ];
    }

    /** Annonces actives dont la période de diffusion est en cours. */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderByDesc('id');
    }

    public function isVisible(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function status(): string
    {
        return match (true) {
            ! $this->is_active                                       => 'Désactivée',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'Programmée',
            $this->ends_at !== null && ! $this->ends_at->isFuture()   => 'Expirée',
            default                                                  => 'En ligne',
        };
    }

    public function styleLabel(): string
    {
        return self::STYLES[$this->style][0] ?? $this->style;
    }

    public function icon(): string
    {
        return self::STYLES[$this->style][1] ?? 'sparkles';
    }

    /**
     * Clé de masquage côté visiteur : change quand l'annonce est modifiée,
     * pour qu'une annonce mise à jour réapparaisse.
     */
    public function dismissKey(): string
    {
        return 'announcement-'.$this->id.'-'.$this->updated_at?->timestamp;
    }
}
