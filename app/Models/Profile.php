<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'first_name', 'last_name', 'headline', 'location', 'email', 'phone',
    'photo_path', 'cv_path', 'about', 'github_url', 'linkedin_url', 'website_url',
])]
class Profile extends Model
{
    protected function casts(): array
    {
        return [
            'about' => 'array',
        ];
    }

    /**
     * Retourne la présentation unique du site, en la créant au besoin.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->first() ?? static::create([]);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name) ?: config('app.name');
    }

    public function initials(): string
    {
        $initials = mb_substr((string) $this->first_name, 0, 1).mb_substr((string) $this->last_name, 0, 1);

        return mb_strtoupper($initials ?: mb_substr(config('app.name'), 0, 2));
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function cvUrl(): ?string
    {
        return $this->cv_path ? Storage::disk('public')->url($this->cv_path) : null;
    }
}
