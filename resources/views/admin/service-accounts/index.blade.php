<x-app-layout>
    <x-slot name="title">Comptes de service et bots</x-slot>
    <x-slot name="header">Comptes de service et bots</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.service-accounts.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouveau compte</a></x-slot>

    <p class="text-sm text-slate-500">
        Les comptes de service permettent à des outils ou scripts d'utiliser l'<strong>API</strong> du portfolio avec des
        <strong>codes d'application</strong>, sans accès au panel. Les <strong>bots</strong> se connectent au panel avec un code
        d'application (page <code>/login/bot</code>) et n'y voient que les sections autorisées. Chaque compte ne dispose que des autorisations que vous lui accordez,
        et toutes ses opérations apparaissent dans le journal d'activité.
    </p>

    @if ($accounts->isEmpty())
        <x-empty-state icon="cog" message="Aucun compte de service ni bot." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Compte</th><th class="hidden md:table-cell">Autorisations</th><th>Codes actifs</th><th class="hidden lg:table-cell">Dernière utilisation</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($accounts as $account)
                        <tr>
                            <td>
                                <a href="{{ route('admin.service-accounts.show', $account) }}" class="font-medium text-slate-900 hover:text-primary-700">{{ $account->name }}</a>
                                <span class="badge-slate ml-1">{{ $account->isBot() ? 'Bot' : 'Service' }}</span>
                                @unless ($account->is_active)<span class="badge bg-red-50 text-red-700 ml-1">désactivé</span>@endunless
                                @if ($account->description)<p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($account->description, 80) }}</p>@endif
                            </td>
                            <td class="hidden text-sm md:table-cell">{{ count($account->permissions ?? []) }}</td>
                            <td class="text-sm">{{ $account->active_tokens_count }}</td>
                            <td class="hidden text-xs text-slate-500 lg:table-cell">{{ $account->last_used_at ? \Illuminate\Support\Carbon::parse($account->last_used_at)->diffForHumans() : 'jamais' }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('admin.service-accounts.show', $account) }}" class="inline-flex items-center justify-center align-middle rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Codes et détails" aria-label="Codes et détails"><x-icon name="eye" class="h-4 w-4" /></a>
                                <x-admin.edit-link :href="route('admin.service-accounts.edit', $account)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @include('admin.service-accounts.partials.api-docs')
</x-app-layout>
