@php
    $user = auth()->user();
    $canCreate = $user->can('panel', 'internship.create');
    $canUpdate = $user->can('panel', 'internship.update');
    $canDelete = $user->can('panel', 'internship.delete');
    $canRemark = $user->can('panel', 'internship_remarks.create');
    $canEditRemark = $user->can('panel', 'internship_remarks.update');
    $canDeleteRemark = $user->can('panel', 'internship_remarks.delete');
    $filtered = $status || $search !== '';
@endphp
<x-app-layout>
    <x-slot name="title">Recherche de stage</x-slot>
    <x-slot name="header">Recherche de stage</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.internship.export', 'pdf') }}" class="btn-secondary" title="Exporter en PDF"><x-icon name="download" class="h-4 w-4" /> PDF</a>
        <a href="{{ route('admin.internship.export', 'excel') }}" class="btn-secondary" title="Exporter en Excel"><x-icon name="table" class="h-4 w-4" /> Excel</a>
        @if ($canCreate)
            <a href="{{ route('admin.internship.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a>
        @endif
    </x-slot>

    {{-- Indicateurs --}}
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Entreprises contactées</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['total'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Réponses attendues</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $stats['awaiting'] }}</p>
        </div>
        <a href="{{ route('admin.internship.index', ['tri' => 'relance']) }}" @class(['card p-4 transition hover:border-primary-300', 'ring-2 ring-red-200' => $stats['due']])>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Relances à faire</p>
            <p @class(['mt-1 text-2xl font-bold', 'text-red-600' => $stats['due'], 'text-slate-900' => ! $stats['due']])>{{ $stats['due'] }}</p>
        </a>
        <div class="card p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Acceptées · taux de réponse</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $stats['accepted'] }} <span class="text-sm font-medium text-slate-500">· {{ $stats['responses'] }} %</span></p>
        </div>
    </div>

    <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section class="min-w-0 space-y-4" aria-labelledby="contacts-title">
            <h2 id="contacts-title" class="sr-only">Entreprises contactées</h2>

            {{-- Filtres --}}
            <nav class="flex flex-wrap gap-2" aria-label="Filtrer par résultat">
                <a href="{{ route('admin.internship.index', array_filter(['q' => $search, 'tri' => $sort])) }}" @class(['btn-sm', 'btn-primary' => ! $status, 'btn-secondary' => $status])>Tous <span class="opacity-70">{{ $stats['total'] }}</span></a>
                @foreach (\App\Models\InternshipContact::STATUSES as $key => $label)
                    <a href="{{ route('admin.internship.index', array_filter(['statut' => $key, 'q' => $search, 'tri' => $sort])) }}" @class(['btn-sm', 'btn-primary' => $status === $key, 'btn-secondary' => $status !== $key])>{{ $label }} <span class="opacity-70">{{ $counts[$key] ?? 0 }}</span></a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('admin.internship.index') }}" class="flex flex-col gap-2 sm:flex-row">
                @if ($status)<input type="hidden" name="statut" value="{{ $status }}">@endif
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="q" value="{{ $search }}" class="form-input pl-9" placeholder="Entreprise, adresse, interlocuteur, notes…" aria-label="Rechercher">
                </div>
                <select name="tri" class="form-input sm:w-52" aria-label="Trier" onchange="this.form.submit()">
                    @foreach ($sorts as $key => $label)<option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>@endforeach
                </select>
                <button type="submit" class="btn-secondary">Rechercher</button>
                @if ($filtered)<a href="{{ route('admin.internship.index') }}" class="btn-ghost">Réinitialiser</a>@endif
            </form>

            @if ($contacts->isEmpty())
                <x-empty-state icon="briefcase" :message="$filtered ? 'Aucun contact ne correspond à ces critères.' : 'Aucune entreprise contactée pour l\'instant.'">
                    @if (! $filtered && $canCreate)
                        <a href="{{ route('admin.internship.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Ajouter une première entreprise</a>
                    @endif
                </x-empty-state>
            @else
                @if ($filtered)<p class="text-xs text-slate-500">{{ $contacts->count() }} résultat(s) sur {{ $stats['total'] }}.</p>@endif

                {{-- Tableau (écrans larges) --}}
                <div class="card hidden overflow-x-auto lg:block">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Entreprise</th>
                                <th>Contact</th>
                                <th>Résultat</th>
                                <th>Relance</th>
                                <th class="w-1/3">Notes</th>
                                <th class="w-20"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($contacts as $contact)
                                <tr class="align-top">
                                    <td class="py-4">
                                        <p class="font-semibold text-slate-900">{{ $contact->company }}</p>
                                        <p class="mt-0.5 flex items-start gap-1 text-xs text-slate-500"><x-icon name="map-pin" class="mt-px h-3.5 w-3.5 flex-none" /> {{ $contact->address }}</p>
                                        @if ($contact->contact_name)
                                            <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500"><x-icon name="user" class="h-3.5 w-3.5 flex-none" /> {{ $contact->contact_name }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap py-4">
                                        <p class="text-sm text-slate-900">{{ $contact->contacted_on->translatedFormat('j M Y') }}</p>
                                        <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500"><x-icon :name="$contact->methodIcon()" class="h-3.5 w-3.5" /> {{ $contact->methodLabel() }}</p>
                                    </td>
                                    <td class="py-4"><span class="{{ $contact->statusStyle()[0] }} whitespace-nowrap">{{ $contact->statusLabel() }}</span></td>
                                    <td class="whitespace-nowrap py-4">@include('admin.internship.partials.follow-up')</td>
                                    <td class="py-4">@include('admin.internship.partials.notes')</td>
                                    <td class="whitespace-nowrap py-3 text-right">
                                        <x-admin.edit-link can="internship.update" :href="route('admin.internship.edit', $contact)" />
                                        @if ($canDelete)<x-admin.delete-button :action="route('admin.internship.destroy', $contact)" :confirm="'Supprimer « '.$contact->company.' » ?'" />@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Cartes (mobiles et tablettes) --}}
                <ul class="space-y-3 lg:hidden">
                    @foreach ($contacts as $contact)
                        <li class="card p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ $contact->company }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $contact->address }}@if ($contact->contact_name) · {{ $contact->contact_name }}@endif</p>
                                </div>
                                <span class="{{ $contact->statusStyle()[0] }} flex-none">{{ $contact->statusLabel() }}</span>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs text-slate-500">Contact</dt>
                                    <dd class="flex items-center gap-1"><x-icon :name="$contact->methodIcon()" class="h-3.5 w-3.5 text-slate-400" /> {{ $contact->contacted_on->format('d/m/Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-500">Relance</dt>
                                    <dd>@include('admin.internship.partials.follow-up')</dd>
                                </div>
                            </dl>
                            @if ($contact->notes)<div class="mt-3 border-t border-slate-100 pt-3">@include('admin.internship.partials.notes')</div>@endif
                            <div class="mt-2 flex justify-end">
                                <x-admin.edit-link can="internship.update" :href="route('admin.internship.edit', $contact)" />
                                @if ($canDelete)<x-admin.delete-button :action="route('admin.internship.destroy', $contact)" :confirm="'Supprimer « '.$contact->company.' » ?'" />@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Remarques sur l'état général de la recherche (incluses dans les exports) --}}
        <aside class="space-y-4" aria-labelledby="remarks-title" x-data="{ adding: @js($errors->remark->any()), all: false }">
            <div class="flex items-center justify-between gap-2">
                <h2 id="remarks-title" class="font-display text-base font-semibold text-slate-900">Remarques <span class="text-sm font-normal text-slate-500">({{ $remarks->count() }})</span></h2>
                @if ($canRemark)
                    <button type="button" class="btn-secondary btn-sm" @click="adding = ! adding" x-show="! adding"><x-icon name="plus" class="h-4 w-4" /> Ajouter</button>
                @endif
            </div>

            @if ($canRemark)
                <form method="POST" action="{{ route('admin.internship.remarks.store') }}" class="card space-y-3 p-4" x-show="adding" x-cloak>
                    @csrf
                    <label class="form-label" for="remark-date">Date</label>
                    <input id="remark-date" type="date" name="noted_on" value="{{ old('noted_on', today()->toDateString()) }}" class="form-input" required>
                    <label class="form-label" for="remark-body">Remarque</label>
                    <textarea id="remark-body" name="body" rows="4" class="form-input" maxlength="5000" required placeholder="Situation actuelle de la recherche, difficultés, pistes…">{{ old('body') }}</textarea>
                    @foreach ($errors->remark->all() as $message)<p class="form-error">{{ $message }}</p>@endforeach
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-secondary btn-sm" @click="adding = false">Annuler</button>
                        <button type="submit" class="btn-primary btn-sm"><x-icon name="check" class="h-4 w-4" /> Ajouter</button>
                    </div>
                </form>
            @endif

            @if ($remarks->isEmpty())
                <div class="card px-4 py-8 text-center text-sm text-slate-500">
                    <x-icon name="document" class="mx-auto mb-2 h-6 w-6 text-slate-300" />
                    Aucune remarque. Elles permettent d'expliquer la situation de la recherche et figurent dans les exports.
                </div>
            @else
                {{-- Chronologie, la plus récente en haut --}}
                <ol class="card relative space-y-0 p-4">
                    @foreach ($remarks as $remark)
                        <li class="relative pb-5 pl-6 last:pb-0" x-data="{ editing: false }" @if ($loop->index >= 5) x-show="all" x-cloak @endif>
                            @unless ($loop->last)<span class="absolute left-[5px] top-3 h-full w-px bg-slate-200" aria-hidden="true"></span>@endunless
                            <span @class(['absolute left-0 top-1.5 h-[11px] w-[11px] rounded-full ring-4 ring-white', 'bg-primary-500' => $loop->first, 'bg-slate-300' => ! $loop->first]) aria-hidden="true"></span>

                            <div x-show="! editing">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold text-slate-500">
                                        <time datetime="{{ $remark->noted_on->toDateString() }}">{{ $remark->noted_on->translatedFormat('j F Y') }}</time>
                                        @if ($loop->first)<span class="badge-primary ml-1">Dernière</span>@endif
                                    </p>
                                    <div class="-my-1 flex flex-none">
                                        @if ($canEditRemark)
                                            <button type="button" class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-400 hover:bg-primary-50 hover:text-primary-600" @click="editing = true" title="Modifier" aria-label="Modifier">
                                                <x-icon name="pencil" class="h-4 w-4" />
                                            </button>
                                        @endif
                                        @if ($canDeleteRemark)
                                            <x-admin.delete-button class="!p-1.5" :action="route('admin.internship.remarks.destroy', $remark)" confirm="Supprimer cette remarque ?" />
                                        @endif
                                    </div>
                                </div>
                                <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $remark->body }}</p>
                            </div>

                            @if ($canEditRemark)
                                <form method="POST" action="{{ route('admin.internship.remarks.update', $remark) }}" class="space-y-2" x-show="editing" x-cloak>
                                    @csrf
                                    @method('PUT')
                                    <input type="date" name="noted_on" value="{{ $remark->noted_on->toDateString() }}" class="form-input" required aria-label="Date">
                                    <textarea name="body" rows="4" class="form-input" maxlength="5000" required aria-label="Remarque">{{ $remark->body }}</textarea>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="btn-secondary btn-sm" @click="editing = false">Annuler</button>
                                        <button type="submit" class="btn-primary btn-sm"><x-icon name="check" class="h-4 w-4" /> Enregistrer</button>
                                    </div>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ol>
                @if ($remarks->count() > 5)
                    <button type="button" class="btn-ghost btn-sm w-full" @click="all = ! all" x-text="all ? 'Afficher moins' : 'Afficher les {{ $remarks->count() - 5 }} plus anciennes'"></button>
                @endif
            @endif
        </aside>
    </div>
</x-app-layout>
