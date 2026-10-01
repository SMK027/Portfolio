<x-app-layout>
    <x-slot name="title">Historique — {{ $article->title }}</x-slot>
    <x-slot name="header">Historique des versions</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.articles.show', $article) }}" class="btn-secondary"><x-icon name="arrow-left" class="h-4 w-4" /> Retour à l'article</a>
    </x-slot>

    <p class="text-sm text-slate-500">
        « {{ $article->title }} » — une version est enregistrée à chaque modification du titre, du résumé ou du contenu
        ({{ \App\Models\ArticleRevision::KEEP }} dernières conservées). Les pièces jointes, thèmes et réglages de publication ne sont pas versionnés.
    </p>

    <div class="card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Version</th><th class="hidden sm:table-cell">Auteur</th><th class="hidden md:table-cell">Titre</th><th class="w-40"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($revisions as $revision)
                    <tr>
                        <td>
                            <a href="{{ route('admin.articles.revisions.show', [$article, $revision]) }}" class="font-medium text-slate-900 hover:text-primary-700">{{ $revision->created_at->translatedFormat('j F Y à H:i') }}</a>
                            @if ($loop->first)<span class="badge-green ml-1">actuelle</span>@endif
                            @if ($revision->note)<p class="text-xs text-slate-500">{{ $revision->note }}</p>@endif
                        </td>
                        <td class="hidden text-sm sm:table-cell">{{ $revision->user?->name ?? $revision->user_name ?? '—' }}</td>
                        <td class="hidden max-w-xs truncate text-sm md:table-cell">{{ $revision->title }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('admin.articles.revisions.show', [$article, $revision]) }}" class="btn-ghost btn-sm">Comparer</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
