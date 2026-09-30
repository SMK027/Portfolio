<x-app-layout>
    <x-slot name="title">Projets</x-slot>
    <x-slot name="header">Projets</x-slot>
    @can('panel', 'projects.write')
    <x-slot name="actions"><a href="{{ route('admin.projets.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouveau projet</a></x-slot>
    @endcan

    <form method="GET" class="flex gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher un projet…" class="form-input max-w-sm">
        <button class="btn-secondary">Rechercher</button>
    </form>

    @if ($projects->isEmpty())
        <x-empty-state icon="folder" message="Aucun projet." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Projet</th><th class="hidden md:table-cell">Thèmes</th><th class="hidden lg:table-cell">Fichiers</th><th class="w-32"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($projects as $project)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-14 flex-none overflow-hidden rounded bg-slate-100">
                                        @if ($project->thumbnailUrl())<img src="{{ $project->thumbnailUrl() }}" alt="" class="h-full w-full object-cover">@endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-900">{{ $project->title }}</p>
                                        <p class="text-xs text-slate-500">{{ $project->published_on->format('d/m/Y') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell">
                                <div class="flex flex-wrap gap-1">@foreach ($project->themes as $theme)<span class="badge-primary">{{ $theme->name }}</span>@endforeach</div>
                            </td>
                            <td class="hidden lg:table-cell">{{ $project->files_count }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('projects.show', $project) }}" target="_blank" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 inline-block" title="Voir" aria-label="Voir"><x-icon name="eye" class="h-4 w-4" /></a>
                                <x-admin.edit-link can="projects.write" :href="route('admin.projets.edit', $project)" />
                                <x-admin.delete-button can="projects.delete" :action="route('admin.projets.destroy', $project)" confirm="Supprimer ce projet et tous ses fichiers ?" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $projects->links() }}
    @endif
</x-app-layout>
