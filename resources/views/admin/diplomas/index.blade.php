<x-app-layout>
    <x-slot name="title">Diplômes</x-slot>
    <x-slot name="header">Diplômes</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.diplomes.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a></x-slot>

    @if ($diplomas->isEmpty())
        <x-empty-state icon="diploma" message="Aucun diplôme pour l'instant." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Diplôme</th><th class="hidden md:table-cell">Obtention</th><th class="hidden sm:table-cell">Ordre</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($diplomas as $diploma)
                        <tr>
                            <td><p class="font-medium text-slate-900">{{ $diploma->title }}</p><p class="text-xs text-slate-500">{{ $diploma->institution }}@if ($diploma->level) · {{ $diploma->level }}@endif</p></td>
                            <td class="hidden md:table-cell">{{ $diploma->obtained_at->format('m/Y') }}</td>
                            <td class="hidden sm:table-cell">{{ $diploma->position }}</td>
                            <td class="whitespace-nowrap text-right">
                                <x-admin.edit-link :href="route('admin.diplomes.edit', $diploma)" />
                                <x-admin.delete-button :action="route('admin.diplomes.destroy', $diploma)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
