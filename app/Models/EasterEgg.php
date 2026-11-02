<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Élément de la page d'easter eggs : affiché seulement si ses conditions sont remplies
 * (App\Services\EasterEggs::isVisible), et verrouillé tant que sa combinaison n'est pas tapée.
 */
#[Fillable([
    'title', 'type', 'body', 'link_url', 'link_label', 'effect', 'unlock_sequence', 'hint',
    'starts_at', 'ends_at', 'weekdays', 'time_from', 'time_to', 'device', 'countries', 'country_mode',
    'min_unlocked', 'is_active', 'position',
])]
class EasterEgg extends Model
{
    use Auditable;

    /** Types : libellé et icône. */
    public const TYPES = [
        'text'   => ['Texte', 'document'],
        'image'  => ['Image / GIF', 'photo'],
        'link'   => ['Lien', 'link'],
        'effect' => ['Effet visuel', 'sparkles'],
    ];

    public const EFFECTS = [
        'confetti'    => 'Confettis',
        'matrix'      => 'Pluie « Matrix »',
        'barrel-roll' => 'Tonneau (la page fait un tour)',
        'invert'      => 'Couleurs inversées',
    ];

    public const DEVICES = ['mobile' => 'Mobile uniquement', 'desktop' => 'Ordinateur uniquement'];

    protected function casts(): array
    {
        return [
            'starts_at'    => 'datetime',
            'ends_at'      => 'datetime',
            'weekdays'     => 'array',
            'countries'    => 'array',
            'is_active'    => 'boolean',
            'min_unlocked' => 'integer',
        ];
    }

    /** Images (carrousel), dans l'ordre d'ajout. */
    public function images(): HasMany
    {
        return $this->hasMany(EasterEggImage::class)->orderBy('position')->orderBy('id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function isLocked(): bool
    {
        return filled($this->unlock_sequence);
    }

    /** Conditions de date, de jour et d'horaire (dans le fuseau du site). */
    public function isScheduledAt(Carbon $now): bool
    {
        if (($this->starts_at && $this->starts_at->gt($now)) || ($this->ends_at && $this->ends_at->lte($now))) {
            return false;
        }
        if ($this->weekdays && ! in_array($now->isoWeekday(), array_map('intval', $this->weekdays), true)) {
            return false;
        }
        if ($this->time_from && $this->time_to) {
            $time = $now->format('H:i');

            // Plage qui passe minuit (ex. 22:00 → 02:00).
            return $this->time_from <= $this->time_to
                ? $time >= $this->time_from && $time < $this->time_to
                : $time >= $this->time_from || $time < $this->time_to;
        }

        return true;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function icon(): string
    {
        return self::TYPES[$this->type][1] ?? 'sparkles';
    }

    /** Résumé des conditions, pour la liste de l'administration. */
    public function conditionsSummary(): array
    {
        $days = $this->weekdays ? implode(', ', array_map(fn ($d) => mb_substr(AvailabilityRule::WEEKDAYS[(int) $d] ?? '?', 0, 3), $this->weekdays)) : null;

        return array_values(array_filter([
            $this->isLocked() ? 'Combinaison propre' : null,
            $this->starts_at || $this->ends_at ? ($this->starts_at?->format('d/m/Y') ?? '…').' → '.($this->ends_at?->format('d/m/Y') ?? '…') : null,
            $days,
            $this->time_from && $this->time_to ? $this->time_from.'–'.$this->time_to : null,
            $this->device ? self::DEVICES[$this->device] ?? $this->device : null,
            $this->countries ? ($this->country_mode === 'except' ? 'Sauf ' : '').implode(', ', $this->countries) : null,
            $this->min_unlocked ? 'Après '.$this->min_unlocked.' déverrouillé'.($this->min_unlocked > 1 ? 's' : '') : null,
        ]));
    }

    public function auditLabel(): string
    {
        return $this->title;
    }
}
