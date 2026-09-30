<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDatePrecision;
use App\Support\EditorContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'company', 'location', 'contract_type', 'start_date', 'end_date', 'description', 'description_editor', 'description_markdown', 'position'])]
class Experience extends Model
{
    use Auditable, HasDatePrecision;

    /** Suggestions proposées dans le formulaire (saisie libre possible). */
    public const CONTRACT_TYPES = ['CDI', 'CDD', 'Alternance', 'Stage', 'Freelance', 'Intérim', 'Job étudiant', 'Bénévolat'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'description' => 'array',
        ];
    }

    /** Tri : position, puis de la plus récente à la plus ancienne. */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderByDesc('start_date');
    }

    public function hasDescription(): bool
    {
        return ! EditorContent::isEmpty($this->description);
    }

    public function isOngoing(): bool
    {
        return $this->end_date === null || $this->periodEnd($this->end_date)->isFuture();
    }
}
