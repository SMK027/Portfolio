<?php

namespace App\Models;

use App\Models\Concerns\HasDatePrecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'institution', 'location', 'start_date', 'end_date', 'description', 'position'])]
class Education extends Model
{
    use HasDatePrecision;

    protected $table = 'educations';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    /** Tri : position, puis de la plus récente à la plus ancienne. */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderByDesc('start_date');
    }

    public function isOngoing(): bool
    {
        return $this->end_date === null || $this->periodEnd($this->end_date)->isFuture();
    }
}
