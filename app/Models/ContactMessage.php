<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'first_name', 'last_name', 'email', 'subject', 'message',
    'consented_at', 'ip_address', 'recaptcha_score', 'notified_at', 'read_at',
])]
class ContactMessage extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'consented_at'    => 'datetime',
            'read_at'         => 'datetime',
            'notified_at'     => 'datetime',
            'recaptcha_score' => 'float',
        ];
    }

    /**
     * Notification encore dans la file d'attente : les e-mails partent en arrière-plan,
     * avec jusqu'à 3 tentatives sur ~6 minutes (App\Jobs\SendMail).
     */
    public function notificationPending(): bool
    {
        return ! $this->notified_at && config('queue.default') !== 'sync' && $this->created_at?->gt(now()->subMinutes(15));
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }
}
