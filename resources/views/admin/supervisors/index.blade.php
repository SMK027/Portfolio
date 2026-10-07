<x-app-layout>
    <x-slot name="title">Superviseurs</x-slot>
    <x-slot name="header">Superviseurs</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.supervisors.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouveau superviseur</a></x-slot>

    <p class="text-sm text-slate-500">
        Quand un compte personnel, un contributeur ou un bot tente une opération pour laquelle il n'est pas habilité, un superviseur peut la valider
        avec son identifiant et son code PIN. Le bypass ne vaut que pour cette opération, une seule fois. Chaque superviseur est rattaché
        à un administrateur et ne peut valider que les opérations cochées. Toutes les validations sont inscrites au journal d'activité.
    </p>

    @if ($supervisors->isEmpty())
        <x-empty-state icon="lock" message="Aucun superviseur : les opérations non habilitées sont simplement refusées." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Identifiant</th><th>Rattaché à</th><th class="hidden md:table-cell">Opérations</th><th class="hidden lg:table-cell">Dernière validation</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($supervisors as $supervisor)
                        <tr>
                            <td class="font-mono text-sm font-medium text-slate-900">{{ $supervisor->username }}
                                @if (! $supervisor->is_active)<span class="badge-amber ml-1 font-sans">désactivé</span>
                                @elseif (! $supervisor->isUsable())<span class="badge-amber ml-1 font-sans" title="Administrateur rattaché absent ou désactivé">inutilisable</span>@endif
                            </td>
                            <td class="text-sm">{{ $supervisor->user?->name ?? '—' }}</td>
                            <td class="hidden text-sm md:table-cell">{{ count($supervisor->permissions ?? []) }}</td>
                            <td class="hidden text-xs text-slate-500 lg:table-cell">{{ $supervisor->last_used_at?->diffForHumans() ?? 'jamais' }}</td>
                            <td class="whitespace-nowrap text-right">
                                <x-admin.edit-link :href="route('admin.supervisors.edit', $supervisor)" />
                                <x-admin.delete-button :action="route('admin.supervisors.destroy', $supervisor)" confirm="Supprimer ce superviseur ?" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
