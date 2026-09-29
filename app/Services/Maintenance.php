<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Mode maintenance du site, piloté depuis l'administration.
 *
 * Contrairement à "php artisan down" (réponse 503), les visiteurs reçoivent
 * une page de maintenance avec un statut 200, afin de ne pas fausser les
 * outils de surveillance de disponibilité. Les administrateurs continuent
 * de naviguer normalement.
 */
class Maintenance
{
    public const ENABLED = 'maintenance.enabled';

    public const ENDS_AT = 'maintenance.ends_at';

    public const REASON = 'maintenance.reason';

    /**
     * La maintenance est-elle en cours ? Une fois la date de fin atteinte,
     * elle est désactivée automatiquement.
     */
    public function isActive(): bool
    {
        if (! Setting::get(self::ENABLED, false)) {
            return false;
        }

        if ($this->endsAt()?->isPast()) {
            $this->disable();

            return false;
        }

        return true;
    }

    public function endsAt(): ?Carbon
    {
        $value = Setting::get(self::ENDS_AT);

        return $value ? Carbon::parse($value) : null;
    }

    public function reason(): ?string
    {
        return Setting::get(self::REASON) ?: null;
    }

    public function enable(?Carbon $endsAt = null, ?string $reason = null): void
    {
        Setting::set(self::ENDS_AT, $endsAt?->toIso8601String());
        Setting::set(self::REASON, $reason);
        Setting::set(self::ENABLED, true);
    }

    public function disable(): void
    {
        Setting::set(self::ENABLED, false);
    }
}
