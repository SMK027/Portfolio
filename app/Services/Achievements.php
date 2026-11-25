<?php

namespace App\Services;

use App\Achievements\Triggers;
use App\Models\Achievement;
use App\Models\AchievementSettings;
use App\Models\AchievementUnlock;
use App\Models\AchievementVisitor;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Progression des visiteurs et déblocage des succès.
 *
 *   app(Achievements::class)->record('cv_downloaded');
 *   app(Achievements::class)->record('projects_viewed', (string) $project->id);
 *
 * Les succès débloqués pendant la requête sont annoncés par le middleware
 * ShowAchievementToasts (page HTML, en-tête de réponse JSON ou page suivante).
 */
class Achievements
{
    /** Cookie fonctionnel portant le jeton du visiteur (13 mois). */
    public const COOKIE = 'ach_vid';

    /** Valeurs distinctes conservées par déclencheur (au-delà, le compteur n'avance plus). */
    protected const MAX_SEEN = 500;

    protected ?AchievementVisitor $visitor = null;

    /** @var list<array{title: string, description: ?string, icon: string}> */
    protected array $pending = [];

    /** Requête à laquelle se rapporte l'état ci-dessus (le service est partagé). */
    protected ?Request $request = null;

    /** Remet l'état à zéro quand la requête change (tests, workers persistants). */
    protected function sync(): Request
    {
        $current = request();
        if ($this->request !== $current) {
            $this->request = $current;
            $this->visitor = null;
            $this->pending = [];
        }

        return $current;
    }

    public function enabled(): bool
    {
        return AchievementSettings::enabled();
    }

    /** Visiteur courant, créé (cookie compris) si demandé. */
    public function visitor(bool $create = false): ?AchievementVisitor
    {
        $this->sync();
        if ($this->visitor) {
            return $this->visitor;
        }

        $token = $this->request->cookie(self::COOKIE);
        if (is_string($token) && Str::isUuid($token)) {
            $this->visitor = AchievementVisitor::where('token', $token)->first();
        }

        if (! $this->visitor && $create) {
            $this->visitor = AchievementVisitor::create(['token' => (string) Str::uuid(), 'progress' => [], 'seen' => []]);
        }

        if ($this->visitor && $create) {
            Cookie::queue(self::COOKIE, $this->visitor->token, 60 * 24 * 395, httpOnly: true, sameSite: 'lax');
        }

        return $this->visitor;
    }

    /** Les robots ne débloquent rien. */
    public function eligible(): bool
    {
        $this->sync();

        return $this->enabled() && filled($this->request->userAgent()) && ! UserAgent::isBot($this->request->userAgent());
    }

    /**
     * Fait avancer un déclencheur : +$amount, ou nouvelle valeur distincte si $value est donnée.
     *
     * @return list<Achievement> succès débloqués par cet appel
     */
    public function record(string $trigger, ?string $value = null, int $amount = 1): array
    {
        if (! isset(Triggers::ALL[$trigger]) || ! $this->eligible()) {
            return [];
        }

        try {
            $visitor = $this->visitor(create: true);
            $progress = $visitor->progress ?? [];

            if (Triggers::isDistinct($trigger)) {
                $seen = $visitor->seen ?? [];
                $values = $seen[$trigger] ?? [];
                if ($value === null || in_array($value, $values, true) || count($values) >= self::MAX_SEEN) {
                    return $this->unlockReached($visitor, $trigger);
                }
                $values[] = mb_substr($value, 0, 64);
                $seen[$trigger] = $values;
                $visitor->seen = $seen;
                $progress[$trigger] = count($values);
            } else {
                $progress[$trigger] = ($progress[$trigger] ?? 0) + max(1, $amount);
            }

            $visitor->progress = $progress;
            $visitor->save();

            return $this->unlockReached($visitor, $trigger);
        } catch (\Throwable $e) {
            Log::warning('Succès : progression non enregistrée — '.$e->getMessage());

            return [];
        }
    }

    /** @return list<Achievement> */
    protected function unlockReached(AchievementVisitor $visitor, string $trigger): array
    {
        $unlocked = $visitor->unlocks()->pluck('achievement_id')->all();

        $reached = Achievement::active()->where('trigger', $trigger)
            ->where('threshold', '<=', $visitor->count($trigger))
            ->whereNotIn('id', $unlocked)->ordered()->get();

        foreach ($reached as $achievement) {
            AchievementUnlock::firstOrCreate(
                ['achievement_id' => $achievement->id, 'achievement_visitor_id' => $visitor->id],
                ['unlocked_at' => now()],
            );
            $this->pending[] = ['title' => $achievement->title, 'description' => $achievement->description, 'icon' => $achievement->icon];
        }

        $reached = $reached->all();

        // Succès « méta » : nombre de succès débloqués (hors méta eux-mêmes).
        if ($reached && $trigger !== 'achievements_unlocked') {
            $progress = $visitor->progress ?? [];
            $progress['achievements_unlocked'] = $visitor->unlocks()
                ->whereHas('achievement', fn ($q) => $q->where('trigger', '!=', 'achievements_unlocked'))->count();
            $visitor->progress = $progress;
            $visitor->save();
            $reached = [...$reached, ...$this->unlockReached($visitor, 'achievements_unlocked')];
        }

        return $reached;
    }

    /** Succès débloqués pendant la requête, puis vidés. */
    public function pullPending(): array
    {
        $this->sync();

        return tap($this->pending, fn () => $this->pending = []);
    }

    /**
     * Succès affichables pour le visiteur courant, avec progression.
     *
     * @return Collection<int, array{achievement: Achievement, unlocked_at: ?\Illuminate\Support\Carbon, current: int, rarity: float}>
     */
    public function board(): Collection
    {
        $visitor = $this->visitor();
        $unlocks = $visitor ? $visitor->unlocks()->pluck('unlocked_at', 'achievement_id') : collect();
        $visitors = max(1, AchievementVisitor::count());

        return Achievement::active()->ordered()->withCount('unlocks')->get()->map(fn (Achievement $a) => [
            'achievement' => $a,
            'unlocked_at' => isset($unlocks[$a->id]) ? \Illuminate\Support\Carbon::parse($unlocks[$a->id]) : null,
            'current'     => min($a->threshold, $visitor?->count($a->trigger) ?? 0),
            'rarity'      => round($a->unlocks_count / $visitors * 100, 1),
        ]);
    }
}
