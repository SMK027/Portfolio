<?php

namespace App\Models\Concerns;

use App\Support\PreciseDate;
use Illuminate\Support\Carbon;

/**
 * Dates affichées selon la précision choisie (colonne date_precision).
 */
trait HasDatePrecision
{
    protected function initializeHasDatePrecision(): void
    {
        $this->mergeFillable(['date_precision']);
    }

    public function datePrecision(): string
    {
        return $this->date_precision ?: PreciseDate::DAY;
    }

    public function formatDate(?Carbon $date, bool $short = false): ?string
    {
        return PreciseDate::format($date, $this->datePrecision(), $short);
    }

    /** Valeur du champ de formulaire pour la précision donnée. */
    public function inputDate(?Carbon $date, string $precision): ?string
    {
        return $date ? PreciseDate::inputValue($date, $precision) : null;
    }

    protected function periodEnd(Carbon $date): Carbon
    {
        return PreciseDate::periodEnd($date, $this->datePrecision());
    }
}
