{{-- Sélection multiple sous forme de pastilles cochables --}}
@props(['name', 'label' => null, 'options' => [], 'selected' => [], 'help' => null, 'empty' => 'Aucun élément disponible.'])
@php $current = collect(old($name, $selected))->map(fn ($v) => (string) $v)->all(); @endphp
<fieldset {{ $attributes->only('class') }}>
    @if ($label)<legend class="form-label">{{ $label }}</legend>@endif
    <div class="flex flex-wrap gap-2">
        @forelse ($options as $optionValue => $optionLabel)
            <label class="cursor-pointer">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $optionValue }}" class="peer sr-only" @checked(in_array((string) $optionValue, $current, true))>
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1 text-sm text-slate-600 transition peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:text-primary-700 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500">
                    {{ $optionLabel }}
                </span>
            </label>
        @empty
            <p class="text-sm text-slate-500">{{ $empty }}</p>
        @endforelse
    </div>
    @if ($help)<p class="form-help">{{ $help }}</p>@endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="form-error">{{ $message }}</p>@enderror
</fieldset>
