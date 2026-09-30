{{-- Cases à cocher des sections d'import / export (toutes cochées par défaut) --}}
@props(['sections'])
<fieldset {{ $attributes->only('class') }} x-data="{ toggle(state) { $el.querySelectorAll('input[type=checkbox]').forEach((c) => c.checked = state) } }">
    <div class="mb-2 flex items-center justify-between">
        <legend class="form-label mb-0">Sections</legend>
        <span class="text-xs">
            <button type="button" class="text-primary-600 hover:underline" @click="toggle(true)">Tout</button> ·
            <button type="button" class="text-primary-600 hover:underline" @click="toggle(false)">Aucune</button>
        </span>
    </div>
    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
        @foreach ($sections as $key => $label)
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="sections[]" value="{{ $key }}" checked class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                {{ $label }}
            </label>
        @endforeach
    </div>
</fieldset>
