<x-app-layout>
    <x-slot name="title">Comptes</x-slot>
    <x-slot name="header">Comptes</x-slot>
    @can('manage-users', [null])
        <x-slot name="actions"><a href="{{ route('admin.utilisateurs.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouveau compte</a></x-slot>
    @endcan

    <p class="text-sm text-slate-500">
        Les <strong>administrateurs</strong> gèrent le contenu et voient les pages privées. Les <strong>contributeurs</strong> accèdent uniquement
        à la rédaction d'articles : ils consultent tous les articles, modifient ceux dont ils sont auteurs ou co-auteurs, et leurs
        nouveaux articles sont publiés après validation par un administrateur. Le <strong>personnel</strong> n'accède qu'aux sections du panel
        que vous lui autorisez, avec une désactivation programmable. Seuls les super-administrateurs gèrent les comptes.
    </p>

    <div class="card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Nom</th><th class="hidden md:table-cell">E-mail</th><th>Rôle</th><th class="hidden sm:table-cell">Articles</th><th class="w-24"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td><p class="font-medium text-slate-900">{{ $user->name }}@if ($user->hasTwoFactor()) <span class="badge-green ml-1" title="Double authentification activée">2FA</span>@endif</p><p class="text-xs text-slate-500">{{ '@'.$user->username }}</p></td>
                        <td class="hidden md:table-cell">{{ $user->email }}</td>
                        <td>
                            <span @class(['badge-primary' => $user->isAdmin(), 'badge-slate' => ! $user->isAdmin()])>{{ \Illuminate\Support\Str::before($user->roleLabel(), ' (') }}</span>
                            @if (! $user->isActive())
                                <span class="badge ml-1 bg-red-50 text-red-700">désactivé</span>
                            @elseif ($user->deactivates_at)
                                <span class="badge-amber ml-1" title="Désactivation programmée">jusqu'au {{ $user->deactivates_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </td>
                        <td class="hidden sm:table-cell">{{ $user->articles_count }}</td>
                        <td class="whitespace-nowrap text-right">
                            @can('manage-users', $user)
                                <x-admin.edit-link :href="route('admin.utilisateurs.edit', $user)" />
                            @endcan
                            @can('manage-users', [$user, 'users.delete'])
                                @unless ($user->is(auth()->user()))
                                    <x-admin.delete-button :action="route('admin.utilisateurs.destroy', $user)" confirm="Supprimer ce compte ?" />
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
