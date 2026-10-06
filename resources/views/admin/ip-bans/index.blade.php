<x-app-layout>
    <x-slot name="title">Adresses IP bannies</x-slot>
    <x-slot name="header">Adresses IP bannies</x-slot>

    <p class="text-sm text-slate-500">
        Après {{ config('auth.login_ban.max_attempts') }} échecs de connexion en {{ config('auth.login_ban.window') }} minutes
        (mot de passe, clé de sécurité, code de bot ou second facteur), l'adresse IP est bannie de tout le site, API comprise,
        pendant {{ config('auth.login_ban.duration') }} minutes. Chaque bannissement, modification et levée est enregistré dans le
        <a href="{{ route('admin.audit.index', ['category' => 'auth']) }}" class="font-medium text-primary-600 underline">journal d'activité</a>
        et dans <code class="rounded bg-slate-100 px-1">storage/logs/security-*.log</code>.
        Votre adresse actuelle : <code class="rounded bg-slate-100 px-1">{{ $myIp }}</code>.
    </p>
    <p class="text-sm text-slate-500">
        @if ($whitelist = config('auth.login_ban.whitelist'))
            Jamais bannies (liste blanche) :
            @foreach ($whitelist as $entry)<code class="rounded bg-slate-100 px-1">{{ $entry }}</code>@if (! $loop->last), @endif @endforeach.
        @else
            Aucune liste blanche :
        @endif
        Pour ne jamais bannir vos propres adresses, renseignez <code class="rounded bg-slate-100 px-1">LOGIN_BAN_WHITELIST</code> dans le fichier <code>.env</code>
        (IP ou plages CIDR séparées par des virgules).
    </p>

    <x-admin.section title="Bannissements en cours">
        @if ($active->isEmpty())
            <p class="text-sm text-slate-500">Aucune adresse IP n'est bannie actuellement.</p>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="admin-table">
                    <thead><tr><th>Adresse IP</th><th>Depuis</th><th>Jusqu'au</th><th class="hidden md:table-cell">Motif</th><th class="w-24"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($active as $ban)
                            <tr>
                                <td class="font-mono text-sm">{{ $ban->ip_address }}</td>
                                <td class="whitespace-nowrap text-xs text-slate-500">{{ $ban->created_at->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap text-sm">
                                    @if ($ban->banned_until)
                                        {{ $ban->banned_until->format('d/m/Y H:i') }}
                                        <span class="block text-xs text-slate-400">{{ $ban->banned_until->diffForHumans() }}</span>
                                    @else
                                        <span class="badge bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">Sans date de fin</span>
                                    @endif
                                </td>
                                <td class="hidden text-sm text-slate-600 md:table-cell">{{ $ban->reason ?? '—' }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('admin.ip-bans.edit', $ban) }}" class="inline-flex items-center justify-center rounded-lg p-2 align-middle text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Modifier" aria-label="Modifier"><x-icon name="pencil" class="h-4 w-4" /></a>
                                    <form method="POST" action="{{ route('admin.ip-bans.destroy', $ban) }}" class="inline-flex align-middle">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-secondary btn-sm">Lever</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.section>

    <x-admin.section title="Historique" description="Les 50 derniers bannissements terminés. Ils sont supprimés 3 mois après leur fin.">
        @if ($history->isEmpty())
            <p class="text-sm text-slate-500">Aucun bannissement terminé.</p>
        @else
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="admin-table">
                    <thead><tr><th>Adresse IP</th><th>Depuis</th><th>Fin</th><th class="hidden md:table-cell">Motif</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($history as $ban)
                            <tr>
                                <td class="font-mono text-sm">{{ $ban->ip_address }}</td>
                                <td class="whitespace-nowrap text-xs text-slate-500">{{ $ban->created_at->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap text-sm">
                                    @if ($ban->lifted_at)
                                        Levé le {{ $ban->lifted_at->format('d/m/Y H:i') }}
                                        <span class="block text-xs text-slate-400">par {{ $ban->liftedBy?->name ?? 'la console' }}</span>
                                    @else
                                        Expiré le {{ $ban->banned_until->format('d/m/Y H:i') }}
                                    @endif
                                </td>
                                <td class="hidden text-sm text-slate-600 md:table-cell">{{ $ban->reason ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.section>
</x-app-layout>
