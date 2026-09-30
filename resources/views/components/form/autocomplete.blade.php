{{--
    Champ d'auto-complétion (sélection simple ou multiple) filtré côté navigateur.
    options : liste de ['id' => …, 'label' => …, 'hint' => …] (hint : texte d'aide
    recherchable, ex. identifiant ou e-mail).
    channel : nom d'événement partagé entre deux champs ; la valeur d'un champ simple
    y est diffusée et exclue des propositions des champs qui l'écoutent (listen).
--}}
@props([
    'name', 'label' => null, 'options' => [], 'selected' => [], 'multiple' => false,
    'required' => false, 'help' => null, 'placeholder' => 'Rechercher…', 'channel' => null, 'listen' => null,
])
@php
    $selected = collect(old($name, $selected))->filter(fn ($v) => filled($v))->map(fn ($v) => (string) $v)->values()->all();
    $options = collect($options)->map(fn ($o) => ['id' => (string) $o['id'], 'label' => $o['label'], 'hint' => $o['hint'] ?? ''])->values();
    $id = 'ac-'.\Illuminate\Support\Str::slug($name).'-'.\Illuminate\Support\Str::random(4);
@endphp
<div {{ $attributes->only('class') }}
     x-data="{
        options: @js($options), selected: @js($selected), multiple: @js((bool) $multiple),
        query: '', open: false, active: 0, excluded: null,
        init() {
            @if ($channel) this.$watch('selected', v => window.dispatchEvent(new CustomEvent(@js($channel), { detail: v[0] ?? null }))); @endif
        },
        get matches() {
            const q = this.query.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            return this.options.filter(o => ! this.selected.includes(o.id) && o.id !== this.excluded
                && (! q || (o.label + ' ' + o.hint).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').includes(q))).slice(0, 8);
        },
        label(id) { return this.options.find(o => o.id === id)?.label ?? id; },
        pick(option) {
            if (! option) return;
            this.selected = this.multiple ? [...this.selected, option.id] : [option.id];
            this.query = ''; this.active = 0; this.open = this.multiple;
            if (! this.multiple) this.$refs.input.blur();
        },
        remove(id) { this.selected = this.selected.filter(s => s !== id); this.$refs.input.focus(); },
        move(step) { this.open = true; this.active = (this.active + step + this.matches.length) % Math.max(this.matches.length, 1); },
     }"
     @if ($listen) x-on:{{ $listen }}.window="excluded = $event.detail; selected = selected.filter(s => s !== excluded)" @endif
     @click.outside="open = false">
    @if ($label)<label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="text-red-500">*</span>@endif</label>@endif

    <div class="relative">
        <div class="form-input flex min-h-[2.5rem] flex-wrap items-center gap-1.5 py-1.5 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500"
             @click="$refs.input.focus()">
            <template x-for="id in selected" :key="id">
                <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-0.5 text-sm text-primary-700">
                    <span x-text="label(id)"></span>
                    <button type="button" @click.stop="remove(id)" class="rounded-full text-primary-400 hover:text-primary-700" :aria-label="'Retirer ' + label(id)">
                        <x-icon name="x" class="h-3.5 w-3.5" />
                    </button>
                    <input type="hidden" name="{{ $multiple ? $name.'[]' : $name }}" :value="id">
                </span>
            </template>
            <input id="{{ $id }}" type="text" x-ref="input" x-model="query" autocomplete="off"
                   x-show="multiple || selected.length === 0"
                   placeholder="{{ $placeholder }}" role="combobox" :aria-expanded="open.toString()" aria-controls="{{ $id }}-list"
                   @focus="open = true" @input="open = true; active = 0"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                   @keydown.enter.prevent="pick(matches[active])" @keydown.escape="open = false"
                   @keydown.backspace="if (query === '' && selected.length) remove(selected[selected.length - 1])"
                   class="min-w-[8rem] flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0">
            @if (! $multiple)
                <button type="button" x-show="selected.length" x-cloak @click.stop="selected = []; $nextTick(() => $refs.input.focus())"
                        class="ml-auto text-xs font-semibold text-primary-600 hover:text-primary-700">Changer</button>
            @endif
        </div>

        <ul id="{{ $id }}-list" x-show="open && matches.length" x-cloak role="listbox"
            class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
            <template x-for="(option, i) in matches" :key="option.id">
                <li role="option" :aria-selected="(i === active).toString()" @mousedown.prevent="pick(option)" @mouseenter="active = i"
                    :class="i === active ? 'bg-primary-50 text-primary-700' : 'text-slate-700'" class="cursor-pointer px-3 py-2 text-sm">
                    <span x-text="option.label" class="font-medium"></span>
                    <span x-show="option.hint" x-text="option.hint" class="ml-1 text-xs text-slate-400"></span>
                </li>
            </template>
        </ul>
        <p x-show="open && query && ! matches.length" x-cloak class="absolute z-20 mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500 shadow-lg">Aucun compte trouvé.</p>
    </div>

    @if ($help)<p class="form-help">{{ $help }}</p>@endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="form-error">{{ $message }}</p>@enderror
</div>
