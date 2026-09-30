<x-app-layout>
    <x-slot name="title">Veille technologique</x-slot>
    <x-slot name="header">Veille technologique</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.articles.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouvel article</a></x-slot>

    @if (auth()->user()->isContributor())
        <p class="rounded-xl border border-primary-100 bg-primary-50 p-4 text-sm text-primary-900">
            Vous pouvez consulter tous les articles et modifier ceux dont vous êtes auteur ou co-auteur.
            Vos nouveaux articles restent en brouillon jusqu'à leur validation par un administrateur.
        </p>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <nav class="inline-flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-white p-1 text-sm" aria-label="Filtres">
            @foreach (['' => 'Tous', 'a-valider' => 'À valider', 'mes-articles' => 'Mes articles'] as $key => $label)
                <a href="{{ route('admin.articles.index', array_filter(['filtre' => $key, 'q' => request('q')])) }}"
                   @class(['rounded-md px-3 py-1.5 font-medium', 'bg-primary-50 text-primary-700' => $filter === $key, 'text-slate-600 hover:bg-slate-50' => $filter !== $key])>
                    {{ $label }}
                    @if ($key === 'a-valider' && $pendingCount)
                        <span class="ml-1 rounded-full bg-amber-400 px-1.5 text-xs font-semibold text-amber-950">{{ $pendingCount }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
        <form method="GET" class="flex gap-2">
            @if ($filter)<input type="hidden" name="filtre" value="{{ $filter }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher un article…" class="form-input sm:w-64">
            <button class="btn-secondary">Rechercher</button>
        </form>
    </div>

    @if ($articles->isEmpty())
        <x-empty-state icon="newspaper" message="Aucun article." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Article</th><th class="hidden md:table-cell">Auteur(s)</th><th>Statut</th><th class="w-32"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($articles as $article)
                        <tr>
                            <td>
                                <a href="{{ route('admin.articles.show', $article) }}" class="flex items-center gap-1.5 font-medium text-slate-900 hover:text-primary-700">
                                    @if ($article->is_pinned)<x-icon name="pin" class="h-4 w-4 flex-none text-amber-500" />@endif
                                    <span class="truncate">{{ $article->title }}</span>
                                </a>
                                <p class="text-xs text-slate-500">{{ $article->themes->pluck('name')->join(', ') ?: 'Sans thème' }}</p>
                            </td>
                            <td class="hidden text-sm md:table-cell">
                                {{ $article->author?->name }}
                                @if ($article->coauthors->isNotEmpty())<span class="text-slate-400"> + {{ $article->coauthors->count() }}</span>@endif
                            </td>
                            <td>
                                <x-article-status :article="$article" />
                                @if ($article->published_at)<p class="mt-0.5 text-xs text-slate-400">{{ $article->published_at->format('d/m/Y H:i') }}</p>@endif
                                @if ($article->isPendingReview() && $article->submitted_at)<p class="mt-0.5 text-xs text-slate-400">soumis le {{ $article->submitted_at->format('d/m/Y') }}</p>@endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('admin.articles.show', $article) }}" class="inline-flex items-center justify-center align-middle rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Consulter" aria-label="Consulter"><x-icon name="eye" class="h-4 w-4" /></a>
                                @can('update', $article)
                                    <x-admin.edit-link :href="route('admin.articles.edit', $article)" />
                                @endcan
                                @can('delete', $article)
                                    <x-admin.delete-button :action="route('admin.articles.destroy', $article)" />
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $articles->links() }}
    @endif
</x-app-layout>
