<?php

namespace App\Services;

use App\Models\EasterEgg;
use App\Models\EasterEggSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Page d'easter eggs : accès, combinaisons et conditions d'affichage.
 *
 * Accès : un déclencheur (séquence de touches, mot tapé, clics répétés) renvoie un lien signé
 * valable quelques minutes ; l'ouvrir donne un accès de session de même durée. L'adresse seule
 * répond 404. Les combinaisons ne sont envoyées au navigateur que sous forme d'empreinte
 * (SHA-256) : le code de la page ne les révèle pas, le serveur les vérifie à nouveau.
 */
class EasterEggs
{
    protected const ACCESS = 'easter_eggs.access_until';

    protected const UNLOCKED = 'easter_eggs.unlocked';

    public function __construct(protected GeoIp $geoip)
    {
    }

    public function settings(): array
    {
        return EasterEggSettings::current();
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings()['enabled'];
    }

    /**
     * Touches d'une combinaison, normalisées comme dans le navigateur (KeyboardEvent.key en minuscules).
     * « ArrowUp ArrowUp b a » → [arrowup, arrowup, b, a] ; un mot → une touche par lettre.
     *
     * @return list<string>
     */
    public static function keys(?string $sequence, bool $word = false): array
    {
        $sequence = mb_strtolower(trim((string) $sequence));
        if ($sequence === '') {
            return [];
        }

        return $word
            ? mb_str_split(preg_replace('/\s+/u', '', $sequence))
            : array_values(array_filter(preg_split('/\s+/u', $sequence), fn ($k) => $k !== ''));
    }

    /** Empreinte d'une combinaison, identique à celle calculée par le navigateur. */
    public static function fingerprint(array $keys): array
    {
        return ['length' => count($keys), 'hash' => hash('sha256', implode(' ', $keys))];
    }

    /** Déclencheurs transmis aux pages publiques (null si la page est désactivée). */
    public function triggers(): ?array
    {
        $settings = $this->settings();
        if (! $settings['enabled']) {
            return null;
        }

        $sequences = array_values(array_filter([
            ($keys = self::keys($settings['sequence'])) ? self::fingerprint($keys) : null,
            ($keys = self::keys($settings['word'], true)) ? self::fingerprint($keys) : null,
        ]));
        $click = filled($settings['click_selector']) ? ['selector' => $settings['click_selector'], 'count' => max(2, (int) $settings['click_count'])] : null;

        return $sequences || $click ? ['url' => route('easter-eggs.trigger'), 'sequences' => $sequences, 'click' => $click] : null;
    }

    /** Le déclencheur envoyé par le navigateur correspond-il à la configuration ? */
    public function matchesTrigger(array $keys, bool $click): bool
    {
        $settings = $this->settings();
        if (! $settings['enabled']) {
            return false;
        }
        if ($click) {
            return filled($settings['click_selector']);
        }

        $keys = array_map(fn ($k) => mb_strtolower((string) $k), $keys);

        return collect([self::keys($settings['sequence']), self::keys($settings['word'], true)])
            ->filter()->contains(fn (array $expected) => $expected === $keys);
    }

    public function signedUrl(): string
    {
        return URL::temporarySignedRoute('easter-eggs.show', now()->addMinutes($this->minutes()));
    }

    public function grantAccess(Request $request): void
    {
        $request->session()->put(self::ACCESS, now()->addMinutes($this->minutes())->getTimestamp());
    }

    public function hasAccess(Request $request): bool
    {
        return $this->isEnabled() && (int) $request->session()->get(self::ACCESS, 0) > now()->getTimestamp();
    }

    /** @return list<int> */
    public function unlocked(Request $request): array
    {
        return array_map('intval', (array) $request->session()->get(self::UNLOCKED, []));
    }

    public function unlock(Request $request, EasterEgg $egg): void
    {
        $request->session()->put(self::UNLOCKED, array_values(array_unique([...$this->unlocked($request), $egg->id])));
    }

    public function isUnlocked(Request $request, EasterEgg $egg): bool
    {
        return ! $egg->isLocked() || in_array($egg->id, $this->unlocked($request), true);
    }

    /**
     * Éléments affichés pour ce visiteur (verrouillés compris, sans leur contenu).
     *
     * @return Collection<int, EasterEgg>
     */
    public function visibleFor(Request $request): Collection
    {
        return EasterEgg::where('is_active', true)->ordered()->get()->filter(fn (EasterEgg $egg) => $this->isVisible($request, $egg))->values();
    }

    /** Conditions d'affichage : période, jours, horaire, appareil, pays, progression. */
    public function isVisible(Request $request, EasterEgg $egg): bool
    {
        if (! $egg->is_active || ! $egg->isScheduledAt(now())) {
            return false;
        }
        if ($egg->device && $egg->device !== ($this->isMobile($request) ? 'mobile' : 'desktop')) {
            return false;
        }
        if ($egg->countries) {
            $inList = in_array($this->geoip->country($request->ip(), $request->header('CF-IPCountry')), $egg->countries, true);
            if ($egg->country_mode === 'except' ? $inList : ! $inList) {
                return false;
            }
        }

        // Progression : éléments déjà déverrouillés (lui-même exclu).
        return count(array_diff($this->unlocked($request), [$egg->id])) >= $egg->min_unlocked;
    }

    public function isMobile(Request $request): bool
    {
        return (bool) preg_match('/Mobi|Android|iPhone|iPad|iPod/i', (string) $request->userAgent());
    }

    protected function minutes(): int
    {
        return max(1, (int) $this->settings()['access_minutes']);
    }
}
