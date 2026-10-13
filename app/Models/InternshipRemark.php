<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Remarque sur l'état général de la recherche de stage. */
#[Fillable(['noted_on', 'body'])]
class InternshipRemark extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['noted_on' => 'date'];
    }

    public function auditLabel(): string
    {
        return 'Remarque du '.$this->noted_on?->format('d/m/Y');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('noted_on')->orderByDesc('id');
    }
}
