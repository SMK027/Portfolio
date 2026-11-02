<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Image d'un easter egg, stockée sur le disque privé et servie par EasterEggController::image. */
#[Fillable(['path', 'position'])]
class EasterEggImage extends Model
{
    public function egg(): BelongsTo
    {
        return $this->belongsTo(EasterEgg::class, 'easter_egg_id');
    }

    public function url(): string
    {
        return route('easter-eggs.image', [$this->easter_egg_id, $this]);
    }
}
