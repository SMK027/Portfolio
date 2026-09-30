<x-app-layout>
    <x-slot name="title">Compétences</x-slot>
    <x-slot name="header">Compétences</x-slot>
    @can('panel', 'skills.write')
    <x-slot name="actions"><a href="{{ route('admin.competences.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a></x-slot>
    @endcan

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
