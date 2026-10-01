<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSettings;
use App\Models\AvailabilityClosure;
use App\Models\AvailabilityRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Créneaux libres calculés à partir des disponibilités et des réservations. */
class AppointmentSlots
{
    /**
     * Créneaux libres groupés par jour (date Y-m-d => liste de débuts).
     *
     * @return Collection<string, Collection<int, CarbonImmutable>>
     */
    public function available(): Collection
    {
        $settings = AppointmentSettings::current();
        $duration = (int) $settings['duration'];
        $earliest = CarbonImmutable::now()->addHours((int) $settings['notice_hours']);
        $last = CarbonImmutable::today()->addDays((int) $settings['horizon_days']);

        $rules = AvailabilityRule::orderBy('start_time')->get()->groupBy('weekday');
        $closed = AvailabilityClosure::where('date', '>=', today())->pluck('date')->map->toDateString()->flip();
        $taken = Appointment::holding()->where('ends_at', '>', $earliest)->get(['starts_at', 'ends_at']);

        $days = collect();
        for ($day = CarbonImmutable::today(); $day->lte($last); $day = $day->addDay()) {
            if (isset($closed[$day->toDateString()])) {
                continue;
            }

            $slots = collect();
            foreach ($rules->get($day->isoWeekday(), []) as $rule) {
                $end = $day->setTimeFromTimeString($rule->end_time);
                for ($start = $day->setTimeFromTimeString($rule->start_time); $start->addMinutes($duration)->lte($end); $start = $start->addMinutes($duration)) {
                    $slotEnd = $start->addMinutes($duration);
                    if ($start->lt($earliest) || $taken->contains(fn ($a) => $a->starts_at->lt($slotEnd) && $a->ends_at->gt($start))) {
                        continue;
                    }
                    $slots->push($start);
                }
            }

            if ($slots->isNotEmpty()) {
                $days[$day->toDateString()] = $slots->unique(fn ($s) => $s->format('H:i'))->sort()->values();
            }
        }

        return $days;
    }

    /** Le créneau fait-il partie des créneaux proposés ? */
    public function isAvailable(CarbonImmutable $start): bool
    {
        return (bool) $this->available()->get($start->toDateString())?->contains(fn ($slot) => $slot->equalTo($start));
    }
}
