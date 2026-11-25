<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Progression d'un visiteur anonyme (jeton aléatoire dans un cookie, aucune donnée personnelle). */
class AchievementVisitor extends Model
{
    protected $fillable = ['token', 'progress', 'seen', 'last_beat_at'];

    protected function casts(): array
    {
        return ['progress' => 'array', 'seen' => 'array', 'last_beat_at' => 'datetime'];
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(AchievementUnlock::class);
    }

    public function progressOf(string $trigger): int
    {
        return (int) ($this->progress[$trigger] ?? 0);
    }
}
