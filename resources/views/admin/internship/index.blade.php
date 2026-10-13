@php
    $statusBadges = ['pending' => 'badge-amber', 'processing' => 'badge-primary', 'accepted' => 'badge-green', 'refused' => 'badge-slate'];
@endphp
<x-app-layout>
    <x-slot name="title">Recherche de stage</x-slot>
    <x-slot name="header">Recherche de stage</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.internship.export', 'pdf') }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> PDF</a>
        <a href="{{ route('admin.internship.export', 'excel') }}" class="btn-secondary"><x-icon name="table" class="h-4 w-4" /> Excel</a>
        <a href="{{ route('admin.internship.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="min-w-0 space-y-4">
            {{-- Filtre par résultat --}}
            <nav class="flex flex-wrap gap-2 text-sm" aria-label="Filtrer par résultat">
                <a href="{{ route('admin.internship.index') }}" @class(['btn-sm', 'btn-primary' => ! $status, 'btn-secondary' => $status])>Tous ({{ $total }})</a>
                @foreach (\App\Models\InternshipContact::STATUSES as $key => $label)
                    <a href="{{ route('admin.internship.index', ['statut' => $key]) }}" @class(['btn-sm', 'btn-primary' => $status === $key, 'btn-secondary' => $status !== $key])>{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
                @endforeach
            </nav>

            @if ($contacts->isEmpty())
                <x-empty-state icon="briefcase" :message="$status ? 'Aucun contact avec ce résultat.' : 'Aucune entreprise contactée pour l\'instant.'" />
            @else
                <div class="card overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Entreprise / organisme</th>
                                <th>Interlocuteur</th>
                                <th>Contact</th>
                                <th>Résultat</th>
                                <th>Relance</th>
                                <th class="min-w-[14rem]">Notes</th>
                                <th class="w-24"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($contacts as $contact)
                                <tr class="align-top">
                                    <td>
                                        <p class="font-medium text-slate-900">{{ $contact->company }}</p>
                                        <p class="text-xs text-slate-500">{{ $contact->address }}</p>
                                    </td>
                                    <td class="text-sm">{{ $contact->contact_name ?: '—' }}</td>
                                    <td class="whitespace-nowrap text-sm">
                                        {{ $contact->contacted_on->format('d/m/Y') }}
                                        <p class="text-xs text-slate-500">{{ $contact->methodLabel() }}</p>
                                    </td>
                                    <td><span class="{{ $statusBadges[$contact->status] ?? 'badge-slate' }} whitespace-nowrap">{{ $contact->statusLabel() }}</span></td>
                                    <td class="whitespace-nowrap text-sm">
                                        @if ($contact->isAwaiting() && $contact->follow_up_on)
                                            <span @class(['font-semibold text-red-600' => $contact->followUpDue()]) @if ($contact->followUpDue()) title="Relance à faire" @endif>{{ $contact->follow_up_on->format('d/m/Y') }}</span>
                                            @if ($contact->followUpMethodLabel())<p class="text-xs text-slate-500">{{ $contact->followUpMethodLabel() }}</p>@endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="whitespace-pre-line text-xs text-slate-600">{{ $contact->notes }}</td>
                                    <td class="whitespace-nowrap text-right">
                                        <x-admin.edit-link :href="route('admin.internship.edit', $contact)" />
                                        <x-admin.delete-button :action="route('admin.internship.destroy', $contact)" confirm="Supprimer ce contact ?" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Remarques sur l'état général de la recherche (incluses dans les exports) --}}
        <aside class="space-y-4">
            <div class="card p-4">
                <h2 class="mb-3 font-semibold text-slate-900">Remarques</h2>
                <form method="POST" action="{{ route('admin.internship.remarks.store') }}" class="space-y-3">
                    @csrf
                    <input type="date" name="noted_on" value="{{ old('noted_on', today()->toDateString()) }}" class="form-input" required aria-label="Date">
                    <textarea name="body" rows="3" class="form-input" maxlength="5000" required placeholder="Situation actuelle de la recherche…" aria-label="Remarque">{{ old('body') }}</textarea>
                    @foreach ($errors->remark->all() as $message)<p class="form-error">{{ $message }}</p>@endforeach
                    <button type="submit" class="btn-primary btn-sm w-full justify-center"><x-icon name="plus" class="h-4 w-4" /> Ajouter la remarque</button>
                </form>
            </div>

            @forelse ($remarks as $remark)
                <div class="card p-4" x-data="{ editing: false }">
                    <div class="flex items-start justify-between gap-2" x-show="! editing">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500">{{ $remark->noted_on->format('d/m/Y') }}</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $remark->body }}</p>
                        </div>
                        <div class="flex flex-none">
                            <button type="button" class="inline-flex items-center justify-center rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="editing = true" title="Modifier" aria-label="Modifier">
                                <x-icon name="pencil" class="h-4 w-4" />
                            </button>
                            <x-admin.delete-button :action="route('admin.internship.remarks.destroy', $remark)" confirm="Supprimer cette remarque ?" />
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.internship.remarks.update', $remark) }}" class="space-y-3" x-show="editing" x-cloak>
                        @csrf
                        @method('PUT')
                        <input type="date" name="noted_on" value="{{ $remark->noted_on->toDateString() }}" class="form-input" required aria-label="Date">
                        <textarea name="body" rows="4" class="form-input" maxlength="5000" required aria-label="Remarque">{{ $remark->body }}</textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-secondary btn-sm" @click="editing = false">Annuler</button>
                            <button type="submit" class="btn-primary btn-sm"><x-icon name="check" class="h-4 w-4" /> Enregistrer</button>
                        </div>
                    </form>
                </div>
            @empty
                <p class="text-center text-sm text-slate-500">Aucune remarque pour l'instant.</p>
            @endforelse
        </aside>
    </div>
</x-app-layout>
