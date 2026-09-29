<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'first_name', 'last_name', 'email', 'subject', 'message',
    'consented_at', 'ip_address', 'recaptcha_score', 'read_at',
])]
class ContactMessage extends Model
{
    protected function casts(): array
    {
        return [
            'consented_at'    => 'datetime',
            'read_at'         => 'datetime',
            'recaptcha_score' => 'float',
        ];
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }
}
