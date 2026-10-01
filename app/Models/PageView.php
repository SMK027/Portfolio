<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Page vue sur le site public (statistiques sans cookie). */
class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['path', 'route', 'referrer_host', 'visitor', 'viewed_on'];

    protected function casts(): array
    {
        return ['viewed_on' => 'date'];
    }
}
