<x-app-layout>
    <x-slot name="title">Expériences</x-slot>
    <x-slot name="header">Expériences professionnelles</x-slot>
    @can('panel', 'experiences.write')
    <x-slot name="actions"><a href="{{ route('admin.experiences.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a></x-slot>
    @endcan

    @if ($experiences->isEmpty())
        <x-empty-state icon="briefcase" message="Aucune expérience pour l'instant." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Poste</th><th class="hidden md:table-cell">Période</th><th class="hidden sm:table-cell">Ordre</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($experiences as $experience)
                        <tr>
                            <td>
                                <p class="font-medium text-slate-900">{{ $experience->title }}</p>
                                <p class="text-xs text-slate-500">{{ $experience->company }}@if ($experience->contract_type) · {{ $experience->contract_type }}@endif</p>
                            </td>
                            <td class="hidden whitespace-nowrap md:table-cell">{{ $experience->formatDate($experience->start_date, true) }} — {{ $experience->formatDate($experience->end_date, true) ?? 'en cours' }}</td>
                            <td class="hidden sm:table-cell">{{ $experience->position }}</td>
                            <td class="whitespace-nowrap text-right">
                                <x-admin.edit-link can="experiences.write" :href="route('admin.experiences.edit', $experience)" />
                                <x-admin.delete-button can="experiences.delete" :action="route('admin.experiences.destroy', $experience)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
