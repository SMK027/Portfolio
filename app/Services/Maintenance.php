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

    public const STARTS_AT = 'maintenance.starts_at';

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
            $this->notify('Maintenance terminée', 'La date de fin est atteinte : le site est de nouveau accessible à tous.');

            return false;
        }

        $startsAt = $this->startsAt();
        if ($startsAt?->isFuture()) {
            return false;
        }

        if ($startsAt) {
            // Début programmé atteint : la maintenance devient une maintenance ordinaire.
            Setting::set(self::STARTS_AT, null);
            $this->notify('Maintenance commencée', 'Le début programmé est atteint : les visiteurs voient la page de maintenance.');
        }

        return true;
    }

    /** Maintenance programmée, pas encore commencée. */
    public function isScheduled(): bool
    {
        return (bool) Setting::get(self::ENABLED, false) && (bool) $this->startsAt()?->isFuture();
    }

    public function startsAt(): ?Carbon
    {
        $value = Setting::get(self::STARTS_AT);

        return $value ? Carbon::parse($value) : null;
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

    public function enable(?Carbon $endsAt = null, ?string $reason = null, ?Carbon $startsAt = null): void
    {
        Setting::set(self::STARTS_AT, $startsAt?->isFuture() ? $startsAt->toIso8601String() : null);
        Setting::set(self::ENDS_AT, $endsAt?->toIso8601String());
        Setting::set(self::REASON, $reason);
        Setting::set(self::ENABLED, true);
    }

    public function disable(): void
    {
        Setting::set(self::ENABLED, false);
        Setting::set(self::STARTS_AT, null);
    }

    protected function notify(string $title, string $description): void
    {
        app(DiscordNotifier::class)->notify('site_status', $title, $description, [], route('admin.maintenance.edit'));
    }
}
