{{--
    Sélection multiple sous forme de pastilles cochables.
    Avec create-url, un bouton permet de créer une nouvelle option à la volée
    (POST JSON { name } → { id, name }) ; elle est ajoutée et cochée.
--}}
@props(['name', 'label' => null, 'options' => [], 'selected' => [], 'help' => null, 'empty' => 'Aucun élément disponible.', 'createUrl' => null, 'createLabel' => 'Nouveau'])
@php $current = collect(old($name, $selected))->map(fn ($v) => (string) $v)->all(); @endphp
<fieldset {{ $attributes->only('class') }}
    @if ($createUrl)
        x-data="{
            adding: false, newName: '', saving: false, error: '', created: [],
            async create() {
                const name = this.newName.trim();
                if (! name || this.saving) return;
                this.saving = true; this.error = '';
                try {
                    const { data } = await window.axios.post(@js($createUrl), { name });
                    const existing = this.$root.querySelector(`input[type=checkbox][value='${data.id}']`);
                    if (existing) { existing.checked = true; }
                    else { this.created.push(data); }
                    this.newName = ''; this.adding = false;
                } catch (e) {
                    this.error = e.response?.data?.errors?.name?.[0] ?? 'Création impossible.';
                } finally { this.saving = false; }
            }
        }"
    @endif>
    @if ($label)<legend class="form-label">{{ $label }}</legend>@endif
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($options as $optionValue => $optionLabel)
            <label class="cursor-pointer">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $optionValue }}" class="peer sr-only" @checked(in_array((string) $optionValue, $current, true))>
                <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1 text-sm text-slate-600 transition peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:text-primary-700 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500">
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach

        @if ($createUrl)
            <template x-for="option in created" :key="option.id">
                <label class="cursor-pointer">
                    <input type="checkbox" name="{{ $name }}[]" :value="option.id" checked class="peer sr-only">
                    <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1 text-sm text-slate-600 transition peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:text-primary-700 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500" x-text="option.name"></span>
                </label>
            </template>

            <button type="button" x-show="! adding" @click="adding = true; $nextTick(() => $refs.newOption.focus())"
                    class="inline-flex items-center gap-1 rounded-full border border-dashed border-slate-300 px-3 py-1 text-sm text-slate-500 hover:border-primary-400 hover:text-primary-700">
                <x-icon name="plus" class="h-3.5 w-3.5" /> {{ $createLabel }}
            </button>
            <span x-show="adding" x-cloak class="inline-flex items-center gap-1">
                <input type="text" x-ref="newOption" x-model="newName" maxlength="100" placeholder="Nom"
                       @keydown.enter.prevent="create()" @keydown.escape.prevent="adding = false"
                       class="form-input w-44 rounded-full py-1 text-sm">
                <button type="button" @click="create()" :disabled="saving" class="btn-primary btn-sm rounded-full">Créer</button>
                <button type="button" @click="adding = false; error = ''" class="btn-ghost btn-sm rounded-full" aria-label="Annuler"><x-icon name="x" class="h-4 w-4" /></button>
            </span>
        @elseif (collect($options)->isEmpty())
            <p class="text-sm text-slate-500">{{ $empty }}</p>
        @endif
    </div>
    @if ($createUrl)<p x-show="error" x-cloak x-text="error" class="form-error"></p>@endif
    @if ($help)<p class="form-help">{{ $help }}</p>@endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="form-error">{{ $message }}</p>@enderror
</fieldset>
