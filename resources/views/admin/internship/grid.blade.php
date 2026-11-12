@php
    use App\Models\InternshipContact;
    $cell = 'form-input min-w-0 px-2 py-1.5 text-xs';
@endphp
<x-app-layout>
    <x-slot name="title">Modifier en masse — Recherche de stage</x-slot>
    <x-slot name="header">Modifier en masse</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.internship.index') }}" class="btn-secondary">Retour à la liste</a>
    </x-slot>

    @if ($errors->any())
        <div class="card border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
            <p class="font-semibold">Aucune modification n'a été enregistrée :</p>
            <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($contacts->isEmpty())
        <x-empty-state icon="briefcase" message="Aucune entreprise contactée pour l'instant." />
    @else
        {{-- Seules les lignes modifiées sont envoyées (les autres champs sont désactivés à l'envoi). --}}
        {{-- Plein écran : la fenêtre entière (mémorisé), et en option le plein écran du navigateur. --}}
        <form method="POST" action="{{ route('admin.internship.grid.update') }}"
              x-data="internshipGrid({{ $errors->any() ? 'true' : 'false' }})"
              :class="full ? 'fixed inset-0 z-[70] flex flex-col gap-3 bg-slate-100 p-3 sm:p-4' : 'space-y-4'"
              @keydown.escape.window="full && ! native && ! $event.target.closest('input, select, textarea') && toggle()"
              @input="markDirty($event)" @change="markDirty($event)"
              @submit="$el.querySelectorAll('tr[data-row]:not([data-dirty]) :is(input, select, textarea)').forEach(field => field.disabled = true)">
            @csrf
            @method('PUT')

            <div class="flex flex-wrap items-center gap-3">
                <p x-show="full" class="font-display text-base font-semibold text-slate-900">Modifier en masse — {{ $contacts->count() }} contact(s)</p>
                <p class="flex-1 basis-80 text-sm text-slate-500">Modifiez directement les cellules, puis enregistrez : seules les lignes modifiées sont prises en compte. La relance prévue est ignorée pour un contact accepté ou refusé.</p>
                <button type="button" class="btn-secondary btn-sm" x-show="full" @click="browserFullscreen()" x-text="native ? 'Quitter le plein écran du navigateur' : 'Plein écran du navigateur'"></button>
                <button type="button" class="btn-secondary btn-sm" @click="toggle()" :title="full ? 'Revenir à la page (Échap)' : 'Occuper toute la fenêtre'">
                    <x-icon name="expand" class="h-4 w-4" /> <span x-text="full ? 'Réduire' : 'Plein écran'"></span>
                </button>
            </div>

            {{-- Filtres dynamiques : appliqués aux valeurs actuelles des cellules, au fil de la frappe --}}
            <div class="flex flex-wrap items-center gap-3 text-xs" @input.stop @change.stop>
                <div class="relative w-full sm:w-72">
                    <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" x-model="filters.all" @input="apply()" placeholder="Filtrer toutes les colonnes…" class="form-input py-1.5 pl-8 text-xs" aria-label="Filtrer toutes les colonnes">
                </div>
                <label class="flex items-center gap-1.5 text-slate-600">
                    <input type="checkbox" x-model="filters.dirtyOnly" @change="apply()" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"> Lignes modifiées seulement
                </label>
                <span class="text-slate-500" x-text="shown + ' / ' + total + ' ligne(s)'"></span>
                <button type="button" x-show="active" x-cloak class="font-medium text-primary-600 hover:underline" @click="reset()">Effacer les filtres</button>
            </div>

            <div class="card overflow-auto" :class="full && 'min-h-0 flex-1'">
                <table class="min-w-[90rem] w-full text-left text-xs">
                    <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            @foreach (['Entreprise *', 'Adresse *', 'Interlocuteur', 'Contact *', 'Moyen *', 'Résultat *', 'Relance', 'Relance prévue', 'Notes'] as $heading)
                                <th class="px-2 py-2.5 first:pl-4">{{ $heading }}</th>
                            @endforeach
                        </tr>
                        <tr class="normal-case tracking-normal" @input.stop @change.stop>
                            @foreach (['Entreprise', 'Adresse', 'Interlocuteur', 'Date de contact', 'Moyen', 'Résultat', 'Relance', 'Relance prévue', 'Notes'] as $column => $label)
                                <th class="px-2 pb-2 font-normal first:pl-4">
                                    @if ($column === 4 || $column === 5)
                                        <select x-model="filters.columns[{{ $column }}]" @change="apply()" class="{{ $cell }} w-full" aria-label="Filtrer : {{ $label }}">
                                            <option value="">Tous</option>
                                            @foreach ($column === 4 ? InternshipContact::METHODS : InternshipContact::STATUSES as $key => $option)<option value="{{ $key }}">{{ $option }}</option>@endforeach
                                        </select>
                                    @elseif (in_array($column, [3, 6, 7], true))
                                        <input type="search" x-model="filters.columns[{{ $column }}]" @input="apply()" placeholder="jj/mm, 2026-10, vide…" class="{{ $cell }} w-full" aria-label="Filtrer : {{ $label }}">
                                    @else
                                        <input type="search" x-model="filters.columns[{{ $column }}]" @input="apply()" placeholder="Filtrer…" class="{{ $cell }} w-full" aria-label="Filtrer : {{ $label }}">
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($contacts as $contact)
                            @php
                                $name = fn ($field) => "contacts[{$contact->id}][{$field}]";
                                $value = fn ($field) => old("contacts.{$contact->id}.{$field}", $contact->{$field} instanceof \DateTimeInterface ? $contact->{$field}->format('Y-m-d') : $contact->{$field});
                                $error = fn ($field) => $errors->has("contacts.{$contact->id}.{$field}") ? 'border-red-400 ring-1 ring-red-400' : '';
                            @endphp
                            <tr data-row @if (old("contacts.{$contact->id}")) data-dirty @endif class="align-top data-[dirty]:bg-amber-50/60">
                                <td class="px-2 py-1.5 pl-4"><input name="{{ $name('company') }}" value="{{ $value('company') }}" class="{{ $cell }} w-48 font-semibold {{ $error('company') }}" required maxlength="150" aria-label="Entreprise"></td>
                                <td class="px-2 py-1.5"><input name="{{ $name('address') }}" value="{{ $value('address') }}" class="{{ $cell }} w-48 {{ $error('address') }}" required maxlength="255" aria-label="Adresse"></td>
                                <td class="px-2 py-1.5"><input name="{{ $name('contact_name') }}" value="{{ $value('contact_name') }}" class="{{ $cell }} w-36 {{ $error('contact_name') }}" maxlength="150" aria-label="Interlocuteur"></td>
                                <td class="px-2 py-1.5"><input type="date" name="{{ $name('contacted_on') }}" value="{{ $value('contacted_on') }}" class="{{ $cell }} w-32 {{ $error('contacted_on') }}" required aria-label="Date de contact"></td>
                                <td class="px-2 py-1.5">
                                    <select name="{{ $name('contact_method') }}" class="{{ $cell }} w-36 {{ $error('contact_method') }}" required aria-label="Moyen de contact">
                                        @foreach (InternshipContact::METHODS as $key => $label)<option value="{{ $key }}" @selected($value('contact_method') === $key)>{{ $label }}</option>@endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-1.5">
                                    <select name="{{ $name('status') }}" class="{{ $cell }} w-40 {{ $error('status') }}" required aria-label="Résultat">
                                        @foreach (InternshipContact::STATUSES as $key => $label)<option value="{{ $key }}" @selected($value('status') === $key)>{{ $label }}</option>@endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-1.5"><input type="date" name="{{ $name('followed_up_on') }}" value="{{ $value('followed_up_on') }}" class="{{ $cell }} w-32 {{ $error('followed_up_on') }}" aria-label="Relance"></td>
                                <td class="px-2 py-1.5"><input type="date" name="{{ $name('follow_up_on') }}" value="{{ $value('follow_up_on') }}" class="{{ $cell }} w-32 {{ $error('follow_up_on') }}" aria-label="Relance prévue"></td>
                                <td class="px-2 py-1.5 pr-4"><textarea name="{{ $name('notes') }}" rows="1" class="{{ $cell }} w-72 {{ $error('notes') }}" maxlength="5000" aria-label="Notes">{{ $value('notes') }}</textarea></td>
                            </tr>
                        @endforeach
                        <tr x-show="! shown" x-cloak><td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">Aucune ligne ne correspond aux filtres.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-2" :class="full ? '' : 'sticky bottom-4'">
                <span x-show="dirty" x-cloak class="mr-auto self-center text-xs text-amber-700">Modifications non enregistrées</span>
                <a href="{{ route('admin.internship.index') }}" class="btn-secondary">Annuler</a>
                <button type="submit" class="btn-primary shadow-lg" :disabled="! dirty"><x-icon name="check" class="h-4 w-4" /> Enregistrer les modifications</button>
            </div>
        </form>
    @endif
</x-app-layout>
