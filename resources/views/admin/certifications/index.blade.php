<x-app-layout>
    <x-slot name="title">Certifications</x-slot>
    <x-slot name="header">Certifications</x-slot>
    @can('panel', 'certifications.write')
    <x-slot name="actions"><a href="{{ route('admin.certifications.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a></x-slot>
    @endcan

    @if ($certifications->isEmpty())
        <x-empty-state icon="badge" message="Aucune certification pour l'instant." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Certification</th><th class="hidden md:table-cell">Obtention</th><th class="hidden md:table-cell">Expiration</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($certifications as $certification)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($certification->badgeUrl())
                                        <img src="{{ $certification->badgeUrl() }}" alt="" class="h-9 w-9 rounded object-contain">
                                    @endif
                                    <div><p class="font-medium text-slate-900">{{ $certification->name }}</p><p class="text-xs text-slate-500">{{ $certification->issuer }}</p></div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell">{{ $certification->formatDate($certification->issued_at, true) }}</td>
                            <td class="hidden md:table-cell">
                                @if ($certification->expires_at)
                                    <span class="{{ $certification->isExpired() ? 'text-red-600' : '' }}">{{ $certification->formatDate($certification->expires_at, true) }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <x-admin.edit-link can="certifications.write" :href="route('admin.certifications.edit', $certification)" />
                                <x-admin.delete-button can="certifications.delete" :action="route('admin.certifications.destroy', $certification)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
