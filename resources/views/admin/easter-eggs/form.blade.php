@php
    $editing = $egg->exists;
    $weekdays = old('weekdays', $egg->weekdays ?? []);
@endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier l\'élément' : 'Nouvel élément' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier l\'élément' : 'Nouvel élément' }}</x-slot>

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.easter-eggs.update', $egg) : route('admin.easter-eggs.store') }}"
          class="space-y-6" x-data="{ type: @js(old('type', $egg->type)) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section title="Contenu">
            <x-form.input name="title" label="Titre" :value="$egg->title" required maxlength="150" />
            <fieldset>
                <legend class="form-label">Type</legend>
                <div class="grid gap-2 sm:grid-cols-4">
                    @foreach (\App\Models\EasterEgg::TYPES as $value => [$label, $icon])
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="{{ $value }}" x-model="type" class="peer sr-only">
                            <span class="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:text-primary-700 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500">
                                <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('type')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
            <x-form.textarea name="body" label="Texte (Markdown)" :value="$egg->body" rows="5" maxlength="10000"
                             help="Obligatoire pour un texte ; légende ou description pour les autres types. Le HTML est ignoré." />
            <div x-show="type === 'image'" x-cloak>
                <x-form.image name="image" label="Image ou GIF animé" :current="$editing && $egg->image_path ? route('easter-eggs.image', $egg) : null"
                              help="10 Mo max. Stockée hors du dossier public : servie uniquement aux visiteurs qui ont trouvé l'élément." />
            </div>
            <div x-show="type === 'link'" x-cloak class="grid gap-5 sm:grid-cols-[1fr_220px]">
                <x-form.input name="link_url" type="url" label="URL" :value="$egg->link_url" maxlength="2048" />
                <x-form.input name="link_label" label="Texte du bouton" :value="$egg->link_label" maxlength="60" placeholder="Ouvrir" />
            </div>
            <div x-show="type === 'effect'" x-cloak>
                <x-form.select name="effect" label="Effet" :value="$egg->effect" :options="\App\Models\EasterEgg::EFFECTS" placeholder="Choisir…" />
            </div>
        </x-admin.section>

        <x-admin.section title="Combinaison propre (facultatif)" description="L'élément apparaît verrouillé (« ??? ») tant que le visiteur n'a pas tapé cette combinaison sur la page.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="unlock_sequence" label="Combinaison" :value="$egg->unlock_sequence" maxlength="255"
                              help="Un mot (« licorne ») ou des touches séparées par des espaces (« ArrowUp ArrowUp Enter »)." />
                <x-form.input name="hint" label="Indice affiché" :value="$egg->hint" maxlength="255" placeholder="Ex. : un animal qui n'existe pas" />
            </div>
        </x-admin.section>

        <x-admin.section title="Conditions d'affichage" description="Toutes les conditions renseignées doivent être remplies. Laissez vide pour ne pas restreindre.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="starts_at" type="datetime-local" label="À partir du" :value="$egg->starts_at?->format('Y-m-d\TH:i')" />
                <x-form.input name="ends_at" type="datetime-local" label="Jusqu'au" :value="$egg->ends_at?->format('Y-m-d\TH:i')" />
            </div>
            <fieldset>
                <legend class="form-label">Jours</legend>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @foreach (\App\Models\AvailabilityRule::WEEKDAYS as $value => $label)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="weekdays[]" value="{{ $value }}" @checked(in_array($value, array_map('intval', $weekdays), true)) class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="form-help">Aucun coché = tous les jours.</p>
            </fieldset>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="time_from" type="time" label="De" :value="$egg->time_from" help="Heure de Paris. Une plage peut passer minuit (22:00 → 02:00)." />
                <x-form.input name="time_to" type="time" label="À" :value="$egg->time_to" />
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.select name="device" label="Appareil" :value="$egg->device" :options="\App\Models\EasterEgg::DEVICES" placeholder="Tous" />
                <x-form.select name="country_mode" label="Pays" :value="$egg->country_mode" :options="['only' => 'Uniquement ces pays', 'except' => 'Sauf ces pays']" required />
                <x-form.input name="countries" label="Codes pays" :value="implode(', ', $egg->countries ?? [])" placeholder="FR, BE, CH" help="Vide = tous les pays." />
            </div>
            <x-form.input name="min_unlocked" type="number" min="0" max="999" label="Progression : visible après N éléments déverrouillés" :value="$egg->min_unlocked"
                         help="0 = dès l'arrivée. Compte les combinaisons d'éléments trouvées par le visiteur." />
        </x-admin.section>

        <x-admin.section title="Publication">
            <x-form.input name="position" type="number" label="Ordre" :value="$egg->position" min="0" max="999" class="sm:w-40" />
            <x-form.checkbox name="is_active" label="Élément actif" :checked="$egg->is_active" />
        </x-admin.section>

        <x-admin.form-actions can="easter_eggs.write" :cancel="route('admin.easter-eggs.index')" />
    </form>
</x-app-layout>
