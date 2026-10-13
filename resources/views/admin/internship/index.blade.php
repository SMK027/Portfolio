@php
    $user = auth()->user();
    $canCreate = $user->can('panel', 'internship.create');
    $canDelete = $user->can('panel', 'internship.delete');
    $canRemark = $user->can('panel', 'internship_remarks.create');
    $canEditRemark = $user->can('panel', 'internship_remarks.update');
    $canDeleteRemark = $user->can('panel', 'internship_remarks.delete');
    $filtered = $status || $search !== '';
    $latest = $remarks->first();
    $older = $remarks->slice(1);
    $barColors = ['pending' => 'bg-amber-400', 'processing' => 'bg-primary-500', 'accepted' => 'bg-emerald-500', 'refused' => 'bg-red-400'];
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

    {{-- Vue d'ensemble : indicateurs et répartition des résultats --}}
    <section class="card p-5" aria-label="Vue d'ensemble">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-5">
            <div>
                <dt class="text-xs font-medium text-slate-500">Entreprises contactées</dt>
                <dd class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['total'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-slate-500">Réponses attendues</dt>
                <dd class="mt-1 text-2xl font-bold text-amber-600">{{ $stats['awaiting'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-slate-500">Relances à faire</dt>
                <dd class="mt-1 flex items-baseline gap-2">
                    <span @class(['text-2xl font-bold', 'text-red-600' => $stats['due'], 'text-slate-900' => ! $stats['due']])>{{ $stats['due'] }}</span>
                    @if ($stats['due'])<a href="{{ route('admin.internship.index', ['tri' => 'relance']) }}" class="text-xs font-medium text-red-600 hover:underline">Voir</a>@endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-slate-500">Acceptées</dt>
                <dd class="mt-1 text-2xl font-bold text-emerald-600">{{ $stats['accepted'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-slate-500">Taux de réponse</dt>
                <dd class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['responses'] }} <span class="text-base font-semibold text-slate-400">%</span></dd>
            </div>
        </dl>

        @if ($stats['total'])
            <div class="mt-5 flex h-2 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Répartition des résultats">
                @foreach (\App\Models\InternshipContact::STATUSES as $key => $label)
                    @if ($counts[$key] ?? 0)
                        <span class="{{ $barColors[$key] }} h-full" style="width: {{ $counts[$key] / $stats['total'] * 100 }}%" title="{{ $label }} : {{ $counts[$key] }}"></span>
                    @endif
                @endforeach
            </div>
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                @foreach (\App\Models\InternshipContact::STATUSES as $key => $label)
                    <li class="flex items-center gap-1.5"><span class="{{ $barColors[$key] }} h-2 w-2 rounded-full"></span> {{ $label }} <span class="font-semibold text-slate-700">{{ $counts[$key] ?? 0 }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Situation actuelle : dernière remarque en avant, historique repliable (inclus dans les exports) --}}
    <section class="card overflow-hidden" aria-labelledby="remarks-title" x-data="{ adding: @js($errors->remark->any()), history: false }">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
            <h2 id="remarks-title" class="font-display text-base font-semibold text-slate-900">Situation de la recherche</h2>
            <div class="flex items-center gap-2">
                @if ($older->isNotEmpty())
                    <button type="button" class="btn-ghost btn-sm" @click="history = ! history" :aria-expanded="history">
                        <x-icon name="clock" class="h-4 w-4" /> <span x-text="history ? 'Masquer l\'historique' : 'Historique ({{ $older->count() }})'"></span>
                    </button>
                @endif
                @if ($canRemark)
                    <button type="button" class="btn-secondary btn-sm" @click="adding = ! adding"><x-icon name="plus" class="h-4 w-4" /> Nouvelle remarque</button>
                @endif
            </div>
        </header>

        @if ($canRemark)
            <form method="POST" action="{{ route('admin.internship.remarks.store') }}" class="space-y-3 border-b border-slate-100 bg-slate-50 px-5 py-4" x-show="adding" x-cloak>
                @csrf
                <div class="grid gap-3 sm:grid-cols-[11rem_1fr]">
                    <div>
                        <label class="form-label" for="remark-date">Date</label>
                        <input id="remark-date" type="date" name="noted_on" value="{{ old('noted_on', today()->toDateString()) }}" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label" for="remark-body">Remarque</label>
                        <textarea id="remark-body" name="body" rows="4" class="form-input" maxlength="5000" required placeholder="Situation actuelle, difficultés rencontrées, pistes…">{{ old('body') }}</textarea>
                    </div>
                </div>
                @foreach ($errors->remark->all() as $message)<p class="form-error">{{ $message }}</p>@endforeach
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary btn-sm" @click="adding = false">Annuler</button>
                    <button type="submit" class="btn-primary btn-sm"><x-icon name="check" class="h-4 w-4" /> Ajouter</button>
                </div>
            </form>
        @endif

        @if (! $latest)
            <p class="px-5 py-6 text-sm text-slate-500">Aucune remarque. Elles expliquent où en est la recherche et figurent dans les exports PDF et Excel.</p>
        @else
            <div class="px-5 py-4">
                @include('admin.internship.partials.remark', ['remark' => $latest, 'highlight' => true])
            </div>
            @if ($older->isNotEmpty())
                <ol class="divide-y divide-slate-100 border-t border-slate-100 bg-slate-50/60" x-show="history" x-cloak>
                    @foreach ($older as $remark)
                        <li class="px-5 py-4">@include('admin.internship.partials.remark', ['highlight' => false])</li>
                    @endforeach
                </ol>
            @endif
        @endif
    </section>

    {{-- Entreprises contactées --}}
    <section class="space-y-4" aria-labelledby="contacts-title">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <h2 id="contacts-title" class="font-display text-base font-semibold text-slate-900">Entreprises contactées</h2>
            <nav class="flex flex-wrap gap-1.5" aria-label="Filtrer par résultat">
                <a href="{{ route('admin.internship.index', array_filter(['q' => $search, 'tri' => $sort])) }}" @class(['btn-sm', 'btn-primary' => ! $status, 'btn-secondary' => $status])>Tous <span class="opacity-70">{{ $stats['total'] }}</span></a>
                @foreach (\App\Models\InternshipContact::STATUS_SHORT as $key => $label)
                    @continue(! ($counts[$key] ?? 0) && $status !== $key)
                    <a href="{{ route('admin.internship.index', array_filter(['statut' => $key, 'q' => $search, 'tri' => $sort])) }}" @class(['btn-sm', 'btn-primary' => $status === $key, 'btn-secondary' => $status !== $key])>{{ $label }} <span class="opacity-70">{{ $counts[$key] ?? 0 }}</span></a>
                @endforeach
            </nav>
        </div>

        <form method="GET" action="{{ route('admin.internship.index') }}" class="flex flex-col gap-2 sm:flex-row">
            @if ($status)<input type="hidden" name="statut" value="{{ $status }}">@endif
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" value="{{ $search }}" class="form-input pl-9" placeholder="Rechercher une entreprise, une ville, un interlocuteur, une note…" aria-label="Rechercher">
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
            @if ($filtered)<p class="text-xs text-slate-500">{{ $contacts->total() }} résultat(s) sur {{ $stats['total'] }}.</p>@endif

            <div class="card overflow-hidden">
                {{-- En-têtes (écrans larges) --}}
                <div class="hidden grid-cols-[minmax(0,1.3fr)_9rem_8rem_9rem_minmax(0,1.4fr)_4.5rem] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid">
                    <span>Entreprise</span>
                    <span>{{ $grouped ? 'Moyen' : 'Contact' }}</span>
                    <span>Résultat</span>
                    <span>Relance</span>
                    <span>Notes</span>
                    <span class="sr-only">Actions</span>
                </div>

                @foreach ($grouped ? $contacts->groupBy(fn ($contact) => $contact->contacted_on->toDateString()) : collect(['' => $contacts->getCollection()]) as $date => $group)
                    @if ($grouped)
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-5 py-1.5 text-xs">
                            <span class="font-semibold text-slate-700">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F Y') }}</span>
                            <span class="text-slate-400">{{ $group->count() }} contact{{ $group->count() > 1 ? 's' : '' }}</span>
                        </div>
                    @endif
                    <ul class="divide-y divide-slate-100 border-b border-slate-100 last:border-b-0">
                        @foreach ($group as $contact)
                            <li class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-4 gap-y-2 px-5 py-3 hover:bg-slate-50 lg:grid-cols-[minmax(0,1.3fr)_9rem_8rem_9rem_minmax(0,1.4fr)_4.5rem] lg:items-center">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900">{{ $contact->company }}</p>
                                    <p class="mt-0.5 flex min-w-0 items-center gap-x-3 text-xs text-slate-500">
                                        <span class="flex min-w-0 items-center gap-1"><x-icon name="map-pin" class="h-3.5 w-3.5 flex-none" /><span class="truncate">{{ $contact->address }}</span></span>
                                        @if ($contact->contact_name)<span class="flex min-w-0 items-center gap-1"><x-icon name="user" class="h-3.5 w-3.5 flex-none" /><span class="truncate">{{ $contact->contact_name }}</span></span>@endif
                                    </p>
                                </div>

                                {{-- Mobile : résultat en haut à droite --}}
                                <span class="{{ $contact->statusStyle()[0] }} justify-self-end lg:hidden">{{ $contact->statusShortLabel() }}</span>

                                <p class="col-span-2 flex items-center gap-1.5 text-sm text-slate-600 lg:col-span-1">
                                    <x-icon :name="$contact->methodIcon()" class="h-4 w-4 flex-none text-slate-400" />
                                    {{ $contact->methodLabel() }}
                                    @unless ($grouped)<span class="text-slate-400">· {{ $contact->contacted_on->format('d/m/Y') }}</span>@endunless
                                </p>

                                <span class="hidden lg:block"><span class="{{ $contact->statusStyle()[0] }}" title="{{ $contact->statusLabel() }}">{{ $contact->statusShortLabel() }}</span></span>

                                <div class="col-span-2 text-sm lg:col-span-1">@include('admin.internship.partials.follow-up')</div>

                                <div class="col-span-2 min-w-0 lg:col-span-1">@include('admin.internship.partials.notes')</div>

                                <div class="col-span-2 -my-1 flex justify-end lg:col-span-1">
                                    <x-admin.edit-link can="internship.update" :href="route('admin.internship.edit', $contact)" />
                                    @if ($canDelete)<x-admin.delete-button :action="route('admin.internship.destroy', $contact)" :confirm="'Supprimer « '.$contact->company.' » ?'" />@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>

            {{ $contacts->links() }}
        @endif
    </section>
</x-app-layout>
