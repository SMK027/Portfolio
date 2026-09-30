{{--
    Champ date dont le format suit la précision choisie (variable Alpine « precision ») :
    année (AAAA), mois (sélecteur de mois) ou date complète. Un seul champ est actif à la fois.
--}}
@props(['name', 'label', 'model', 'field', 'required' => false, 'help' => null])
@php
    use App\Support\PreciseDate;
    $oldPrecision = old('date_precision');
    $current = $oldPrecision ?? $model->datePrecision();
    $valueFor = fn (string $p) => old($name) !== null && $oldPrecision === $p
        ? old($name)
        : $model->inputDate($model->{$field}, $p);
@endphp
<div {{ $attributes->only(['class', 'x-show']) }}>
    <label class="form-label" for="f-{{ $name }}">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    <input id="f-{{ $name }}" x-show="precision === 'year'" @disabled($current !== PreciseDate::YEAR) :disabled="precision !== 'year'" type="number" name="{{ $name }}"
           min="1950" max="2100" step="1" placeholder="AAAA" value="{{ $valueFor(PreciseDate::YEAR) }}" @required($required) class="form-input">
    <input x-show="precision === 'month'" x-cloak @disabled($current !== PreciseDate::MONTH) :disabled="precision !== 'month'" type="month" name="{{ $name }}"
           value="{{ $valueFor(PreciseDate::MONTH) }}" @required($required) class="form-input" aria-label="{{ $label }}">
    <input x-show="precision === 'day'" x-cloak @disabled($current !== PreciseDate::DAY) :disabled="precision !== 'day'" type="date" name="{{ $name }}"
           value="{{ $valueFor(PreciseDate::DAY) }}" @required($required) class="form-input" aria-label="{{ $label }}">
    @if ($help)<p class="form-help">{{ $help }}</p>@endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</div>
