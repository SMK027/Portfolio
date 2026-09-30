<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'first_name', 'last_name', 'headline', 'location', 'email', 'phone',
    'photo_path', 'cv_path', 'about', 'social_links',
])]
class Profile extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'about'        => 'array',
            'social_links' => 'array',
        ];
    }

    /**
     * Retourne la présentation unique du site, en la créant au besoin.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->first() ?? static::create([]);
    }

    /**
     * Réseaux sociaux avec l'icône déduite de l'adresse.
     *
     * @return Collection<int, array{name: string, url: string, icon: string}>
     */
    public function socialLinks(): Collection
    {
        return collect($this->social_links ?? [])
            ->filter(fn ($link) => filled($link['url'] ?? null))
            ->map(fn ($link) => [
                'name' => $link['name'] ?: (string) parse_url($link['url'], PHP_URL_HOST),
                'url'  => $link['url'],
                'icon' => self::iconFor($link['url']),
            ])
            ->values();
    }

    public static function iconFor(string $url): string
    {
        $host = strtolower(preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)));

        return match (true) {
            in_array($host, ['x.com', 'twitter.com'], true) => 'x',
            str_ends_with($host, 'github.com')             => 'github',
            str_ends_with($host, 'linkedin.com')           => 'linkedin',
            str_ends_with($host, 'youtube.com'), $host === 'youtu.be' => 'youtube',
            str_ends_with($host, 'instagram.com')          => 'photo',
            default                                        => 'globe',
        };
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
