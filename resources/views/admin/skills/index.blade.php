<x-app-layout>
    <x-slot name="title">Compétences</x-slot>
    <x-slot name="header">Compétences</x-slot>
    @can('panel', 'skills.write')
    <x-slot name="actions"><a href="{{ route('admin.competences.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a></x-slot>
    @endcan

    @php $canWrite = auth()->user()?->can('panel', 'skills.write'); @endphp
    <details class="card" @if ($errors->category->any()) open @endif>
        <summary class="flex cursor-pointer items-center justify-between px-4 py-3 font-display font-semibold text-slate-900">
            Catégories <span class="text-xs font-normal text-slate-500">{{ $categories->count() }} catégorie(s)</span>
        </summary>
        <div class="space-y-3 border-t border-slate-100 px-4 py-4">
            <p class="text-sm text-slate-500">L'ordre fixe celui des groupes sur le site. Renommer une catégorie avec l'intitulé d'une autre les fusionne ; en supprimer une conserve ses compétences, classées dans « Autres ».</p>
            @foreach ($errors->category->all() as $message)<p class="form-error">{{ $message }}</p>@endforeach

            @foreach ($categories as $category)
                <div class="flex flex-wrap items-center gap-2">
                    <form id="category-{{ $category->id }}" method="POST" action="{{ route('admin.competences.categories.update', $category) }}" class="hidden">@csrf @method('PUT')</form>
                    <input form="category-{{ $category->id }}" name="name" value="{{ $category->name }}" required maxlength="100" class="form-input min-w-0 flex-1" aria-label="Intitulé" @disabled(! $canWrite)>
                    <input form="category-{{ $category->id }}" name="position" type="number" value="{{ $category->position }}" min="0" max="999" class="form-input w-20" aria-label="Ordre" title="Ordre" @disabled(! $canWrite)>
                    <span class="w-24 text-xs text-slate-500">{{ $category->skills_count }} compétence(s)</span>
                    @if ($canWrite)
                        <button form="category-{{ $category->id }}" type="submit" class="inline-flex items-center justify-center rounded-lg p-2 text-slate-400 hover:bg-primary-50 hover:text-primary-600" title="Enregistrer" aria-label="Enregistrer"><x-icon name="check" class="h-4 w-4" /></button>
                    @endif
                    <x-admin.delete-button can="skills.delete" :action="route('admin.competences.categories.destroy', $category)" confirm="Supprimer cette catégorie ? Ses compétences seront conservées, sans catégorie." />
                </div>
            @endforeach

            @if ($canWrite)
                <form method="POST" action="{{ route('admin.competences.categories.store') }}" class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                    @csrf
                    <input name="name" value="{{ old('name') }}" required maxlength="100" placeholder="Nouvelle catégorie" class="form-input min-w-0 flex-1" aria-label="Nouvelle catégorie">
                    <input name="position" type="number" min="0" max="999" placeholder="Ordre" class="form-input w-20" aria-label="Ordre">
                    <button type="submit" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</button>
                </form>
            @endif
        </div>
    </details>

    @forelse ($groups as $category => $skills)
        <div class="card overflow-x-auto">
            <h2 class="border-b border-slate-100 px-4 py-3 font-display font-semibold text-slate-900">{{ $category }}</h2>
            <table class="admin-table">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($skills as $skill)
                        <tr>
                            <td class="font-medium text-slate-900">{{ $skill->name }}</td>
                            <td class="hidden sm:table-cell"><x-skill-level :level="$skill->level" /></td>
                            <td class="hidden text-xs text-slate-500 md:table-cell">{{ $skill->projects_count }} projet(s)</td>
                            <td class="w-24 whitespace-nowrap text-right">
                                <x-admin.edit-link can="skills.write" :href="route('admin.competences.edit', $skill)" />
                                <x-admin.delete-button can="skills.delete" :action="route('admin.competences.destroy', $skill)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <x-empty-state icon="sparkles" message="Aucune compétence pour l'instant." />
    @endforelse
</x-app-layout>
