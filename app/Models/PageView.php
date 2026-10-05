<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Page vue sur le site public (statistiques sans cookie). */
class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['uuid', 'path', 'route', 'referrer_host', 'visitor', 'viewed_on', 'ip_address', 'country', 'device', 'browser', 'os', 'visitor_id', 'session_id', 'is_new_visitor', 'duration'];

    protected function casts(): array
    {
        return ['viewed_on' => 'date', 'is_new_visitor' => 'boolean', 'duration' => 'integer'];
    }
}
