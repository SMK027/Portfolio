{{-- Choix de la précision des dates du formulaire ; pilote la variable Alpine « precision » du formulaire parent --}}
@props(['value' => 'year'])
<fieldset {{ $attributes->only('class') }}>
    <legend class="form-label">Précision des dates</legend>
    <div class="inline-flex flex-wrap gap-2">
        @foreach (\App\Support\PreciseDate::LABELS as $key => $label)
            <label class="cursor-pointer">
                <input type="radio" name="date_precision" value="{{ $key }}" x-model="precision" class="peer sr-only" @checked(old('date_precision', $value) === $key)>
                <span class="block rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:text-primary-700 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500">{{ $label }}</span>
            </label>
        @endforeach
    </div>
    @error('date_precision')<p class="form-error">{{ $message }}</p>@enderror
</fieldset>
