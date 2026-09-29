<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'institution', 'level', 'mention', 'obtained_at', 'description', 'position'])]
class Diploma extends Model
{
    protected function casts(): array
    {
        return [
            'obtained_at' => 'date',
        ];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderByDesc('obtained_at');
    }
}
