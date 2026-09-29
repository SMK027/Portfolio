<x-app-layout>
    <x-slot name="title">Veille technologique</x-slot>
    <x-slot name="header">Veille technologique</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.articles.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouvel article</a></x-slot>

    <form method="GET" class="flex gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher un article…" class="form-input max-w-sm">
        <button class="btn-secondary">Rechercher</button>
    </form>

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
                                <p class="flex items-center gap-1.5 font-medium text-slate-900">
                                    @if ($article->is_pinned)<x-icon name="pin" class="h-4 w-4 flex-none text-amber-500" title="Épinglé" />@endif
                                    <span class="truncate">{{ $article->title }}</span>
                                </p>
                                <p class="text-xs text-slate-500">{{ $article->themes->pluck('name')->join(', ') ?: 'Sans thème' }}</p>
                            </td>
                            <td class="hidden text-sm md:table-cell">
                                {{ $article->author?->name }}
                                @if ($article->coauthors->isNotEmpty())<span class="text-slate-400"> + {{ $article->coauthors->count() }}</span>@endif
                            </td>
                            <td>
                                @php $status = $article->status(); @endphp
                                <span @class(['badge-green' => $status === 'Publié', 'badge-amber' => $status === 'Programmé', 'badge-slate' => $status === 'Brouillon'])>{{ $status }}</span>
                                @if ($article->published_at)<p class="mt-0.5 text-xs text-slate-400">{{ $article->published_at->format('d/m/Y H:i') }}</p>@endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('articles.show', $article) }}" target="_blank" class="inline-block rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Voir / aperçu" aria-label="Voir"><x-icon name="eye" class="h-4 w-4" /></a>
                                <x-admin.edit-link :href="route('admin.articles.edit', $article)" />
                                <x-admin.delete-button :action="route('admin.articles.destroy', $article)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $articles->links() }}
    @endif
</x-app-layout>
