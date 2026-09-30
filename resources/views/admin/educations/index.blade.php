<x-app-layout>
    <x-slot name="title">Formations</x-slot>
    <x-slot name="header">Formations</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.formations.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a></x-slot>

    @if ($educations->isEmpty())
        <x-empty-state icon="academic-cap" message="Aucune formation pour l'instant." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Formation</th><th class="hidden md:table-cell">Période</th><th class="hidden sm:table-cell">Ordre</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($educations as $education)
                        <tr>
                            <td><p class="font-medium text-slate-900">{{ $education->title }}</p><p class="text-xs text-slate-500">{{ $education->institution }}</p></td>
                            <td class="hidden whitespace-nowrap md:table-cell">{{ $education->formatDate($education->start_date, true) }} — {{ $education->formatDate($education->end_date, true) ?? 'en cours' }}</td>
                            <td class="hidden sm:table-cell">{{ $education->position }}</td>
                            <td class="whitespace-nowrap text-right">
                                <x-admin.edit-link :href="route('admin.formations.edit', $education)" />
                                <x-admin.delete-button :action="route('admin.formations.destroy', $education)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
