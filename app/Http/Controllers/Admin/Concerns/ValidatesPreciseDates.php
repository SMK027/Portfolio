<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Support\PreciseDate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation des dates à précision variable (année / mois / jour).
 */
trait ValidatesPreciseDates
{
    /**
     * @param  array<string, bool>  $fields  champ => obligatoire ?
     * @param  array{0: string, 1: string}|null  $range  [début, fin] : la fin ne peut précéder le début
     * @return array<string, mixed>  dates converties + date_precision
     */
    protected function preciseDates(Request $request, array $fields, ?array $range = null): array
    {
        $request->validate(['date_precision' => ['required', Rule::in(array_keys(PreciseDate::LABELS))]]);
        $precision = $request->input('date_precision');

        $rules = [];
        foreach ($fields as $field => $required) {
            $rules[$field] = [$required ? 'required' : 'nullable', PreciseDate::rule($precision)];
        }
        $request->validate($rules, [
            'regex' => 'Le champ :attribute doit être une année sur 4 chiffres.',
        ]);

        $dates = ['date_precision' => $precision];
        foreach (array_keys($fields) as $field) {
            $dates[$field] = PreciseDate::parse($request->input($field), $precision);
        }

        if ($range && $dates[$range[0]] && $dates[$range[1]] && $dates[$range[1]]->lt($dates[$range[0]])) {
            throw ValidationException::withMessages([
                $range[1] => 'Cette date ne peut pas précéder la date de début.',
            ]);
        }

        return $dates;
    }
}
