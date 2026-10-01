<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Clé de sécurité physique ou d'appareil (WebAuthn / FIDO2) d'un compte humain. */
class SecurityKey extends Model
{
    use Auditable;

    protected $fillable = ['name', 'credential_id', 'public_key', 'sign_count', 'last_used_at'];

    protected $hidden = ['public_key'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'sign_count' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
