<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Téléchargement du CV avec un code d'accès (IP effacée après 3 mois). */
#[Fillable(['cv_access_code_id', 'ip_address', 'country', 'city', 'device', 'browser', 'os', 'used_at'])]
class CvAccessCodeUse extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(CvAccessCode::class, 'cv_access_code_id');
    }

    /** Efface les IP de plus de 3 mois, comme pour les statistiques de visite. */
    public static function prune(): int
    {
        return static::whereNotNull('ip_address')->where('used_at', '<', now()->subMonths(3))->update(['ip_address' => null]);
    }
}
