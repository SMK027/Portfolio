@php $changes = collect($diff)->where('type', '!=', 'same')->count(); @endphp
<x-app-layout>
    <x-slot name="title">Version du {{ $revision->created_at->format('d/m/Y H:i') }} — {{ $article->title }}</x-slot>
    <x-slot name="header">Version du {{ $revision->created_at->translatedFormat('j F Y à H:i') }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.articles.revisions.index', $article) }}" class="btn-secondary"><x-icon name="arrow-left" class="h-4 w-4" /> Historique</a>
        @can('update', $article)
            @unless ($isLatest)
                <form method="POST" action="{{ route('admin.articles.revisions.restore', [$article, $revision]) }}" onsubmit="return confirm('Restaurer cette version ? Le texte actuel restera disponible dans l\'historique.')">
                    @csrf
                    <button class="btn-primary"><x-icon name="arrows-updown" class="h-4 w-4" /> Restaurer cette version</button>
                </form>
            @endunless
        @endcan
    </x-slot>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">
            Par {{ $revision->user?->name ?? $revision->user_name ?? '—' }}@if ($revision->note) · {{ $revision->note }}@endif
            · <strong class="text-slate-700">{{ $label }}</strong> : {{ $changes }} ligne(s) modifiée(s)
        </p>
        <div class="flex rounded-lg border border-slate-200 bg-white p-1 text-sm">
            <a href="{{ route('admin.articles.revisions.show', [$article, $revision]) }}" @class(['rounded-md px-3 py-1 font-medium', 'bg-primary-50 text-primary-700' => ! $againstCurrent, 'text-slate-500 hover:text-slate-900' => $againstCurrent])>Avec la précédente</a>
            <a href="{{ route('admin.articles.revisions.show', [$article, $revision, 'avec' => 'actuelle']) }}" @class(['rounded-md px-3 py-1 font-medium', 'bg-primary-50 text-primary-700' => $againstCurrent, 'text-slate-500 hover:text-slate-900' => ! $againstCurrent])>Avec l'actuelle</a>
        </div>
    </div>

    <div class="card overflow-x-auto font-mono text-sm" x-data="{ all: false }">
        @if ($changes === 0)
            <p class="p-4 font-sans text-slate-500">Aucune différence de texte.</p>
        @endif
        @foreach ($diff as $i => $row)
            @php
                // Contexte : lignes inchangées à plus de 2 lignes d'une modification masquées par défaut.
                $near = collect(range(max(0, $i - 2), min(count($diff) - 1, $i + 2)))->contains(fn ($k) => $diff[$k]['type'] !== 'same');
            @endphp
            <div @class([
                'whitespace-pre-wrap break-words px-4 py-0.5',
                'bg-emerald-50 text-emerald-900' => $row['type'] === 'added',
                'bg-red-50 text-red-900 line-through decoration-red-300' => $row['type'] === 'removed',
                'text-slate-600' => $row['type'] === 'same',
            ]) @if ($row['type'] === 'same' && ! $near && $changes) x-show="all" @endif><span class="mr-2 select-none text-slate-400">{{ ['added' => '+', 'removed' => '−', 'same' => ' '][$row['type']] }}</span>{{ $row['line'] }}</div>
        @endforeach
        @if ($changes && collect($diff)->where('type', 'same')->count() > 5)
            <button type="button" @click="all = ! all" class="w-full border-t border-slate-100 py-2 font-sans text-xs font-semibold text-primary-600 hover:bg-slate-50" x-text="all ? 'Masquer le texte inchangé' : 'Afficher tout le texte'"></button>
        @endif
    </div>
</x-app-layout>
