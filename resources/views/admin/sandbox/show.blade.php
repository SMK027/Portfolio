@php
    $mb = fn ($bytes) => $bytes === null ? '—' : number_format($bytes / 1048576, 1, ',', ' ').' Mo';
    $container = $status['container'] ?? [];
    $quota = $status['quota'] ?? null;
@endphp
<x-app-layout>
    <x-slot name="title">Machine SSH</x-slot>
    <x-slot name="header">Machine SSH</x-slot>

    <p class="text-sm text-slate-500">
        Machine Linux bac à sable, isolée du portfolio et volontairement bridée. Les visiteurs s'y connectent par
        <code class="rounded bg-slate-100 px-1">{{ $sshCommand }}</code> ou depuis le terminal web de la page
        <a href="{{ route('admin.pages.index') }}" class="font-medium text-primary-600 hover:underline">« Machine SSH »</a>
        (à rendre publique dans Pages &amp; visibilité). Installation : <code>sandbox/README.md</code>.
    </p>

    @unless ($configured)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Agent non configuré : renseignez <code>SANDBOX_API_URL</code> et <code>SANDBOX_API_TOKEN</code> (32 caractères au moins) dans l'environnement du portfolio.
            Les comptes et réglages sont enregistrés ici et seront appliqués dès que l'agent sera joignable.
        </div>
    @endunless
    @if ($error)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $error }}</div>
    @endif

    @if ($status)
        <x-admin.section title="État">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div><p class="text-xs uppercase tracking-wide text-slate-400">Machine</p>
                    <p class="font-medium"><span @class(['badge-green' => $container['running'] ?? false, 'badge-amber' => ! ($container['running'] ?? false)])>{{ ($container['running'] ?? false) ? 'En marche' : ($container['status'] ?? 'inconnue') }}</span></p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-400">Accès public</p>
                    <p class="font-medium">{{ $status['public'] ? 'Ouvert' : 'Fermé' }}@if ($quota['exceeded'] ?? false) <span class="badge-amber">suspendu (quota)</span>@endif</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-400">Internet</p><p class="font-medium">{{ $status['internet'] ? 'Activé' : 'Coupé' }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-400">Processeur · mémoire</p>
                    <p class="font-medium">{{ $container['cpu_percent'] ?? '—' }} % · {{ $mb($container['memory_bytes'] ?? null) }} / {{ $mb($container['memory_limit_bytes'] ?? null) }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-400">Espace /home</p>
                    @if ($quota && $quota['used_kb'] !== null)
                        @php $pct = min(100, round($quota['used_kb'] / max(1, $quota['limit_kb']) * 100)); @endphp
                        <p class="font-medium">{{ $mb($quota['used_kb'] * 1024) }} / {{ $mb($quota['limit_kb'] * 1024) }}</p>
                        <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div @class(['h-full rounded-full', 'bg-primary-500' => $pct < 90, 'bg-red-500' => $pct >= 90]) style="width: {{ $pct }}%"></div></div>
                    @else <p class="font-medium">—</p> @endif
                </div>
            </div>
            @if ($status['last_error'])<p class="form-error">Dernière erreur de l'agent : {{ $status['last_error'] }}</p>@endif
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.sandbox.sessions.kill') }}">@csrf
                    <button class="btn-secondary btn-sm" onclick="return confirm('Fermer toutes les sessions en cours ?')">Fermer toutes les sessions</button></form>
                <form method="POST" action="{{ route('admin.sandbox.restart') }}">@csrf
                    <button class="btn-secondary btn-sm" onclick="return confirm('Redémarrer la machine ? Les sessions en cours seront coupées.')">Redémarrer la machine</button></form>
            </div>
        </x-admin.section>
    @endif

    <form method="POST" action="{{ route('admin.sandbox.settings') }}">
        @csrf @method('PUT')
        <x-admin.section title="Accès">
            <x-form.checkbox name="public" label="Accès ouvert au public (port SSH et terminal web)" :checked="$settings['public']"
                             help="Fermer l'accès coupe immédiatement toutes les sessions en cours." />
            <x-form.checkbox name="internet" label="Accès internet depuis la machine" :checked="$settings['internet']"
                             help="Coupé, la machine ne joint que son agent. Ouvert, elle sort sur internet (jamais vers les réseaux du portfolio)." />
            <div class="flex flex-wrap items-end gap-5">
                <x-form.checkbox name="auto_backup" label="Sauvegarde automatique chaque nuit (4 h 15)" :checked="$settings['auto_backup']" />
                <x-form.input name="keep" type="number" label="Sauvegardes automatiques conservées" :value="$settings['keep']" min="1" max="60" class="w-56" />
            </div>
            <div class="flex justify-end"><button class="btn-primary"><x-icon name="check" class="h-4 w-4" /> Enregistrer</button></div>
        </x-admin.section>
    </form>

    <x-admin.section title="Comptes" description="Le compte invité est affiché avec son mot de passe sur la page publique ; les comptes nominatifs sont à transmettre vous-même.">
        @if ($accounts->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th>Compte</th><th>Statut</th><th class="hidden md:table-cell">Expiration</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($accounts as $account)
                            <tr x-data="{ edit: false }">
                                <td>
                                    <p class="font-mono font-medium text-slate-900">{{ $account->username }} @if ($account->is_guest)<span class="badge-amber font-sans">Invité</span>@endif</p>
                                    @if ($account->label)<p class="text-xs text-slate-500">{{ $account->label }}</p>@endif
                                    @if ($account->is_guest && $account->guestPassword())<p class="text-xs text-slate-500">Mot de passe : <span class="font-mono">{{ $account->guestPassword() }}</span></p>@endif
                                    <form x-show="edit" x-cloak method="POST" action="{{ route('admin.sandbox.accounts.update', $account) }}" class="mt-3 space-y-3">
                                        @csrf @method('PUT')
                                        <x-form.input name="label" label="Description" :value="$account->label" maxlength="150" :id="'label-'.$account->id" />
                                        <x-form.input name="expires_at" type="date" label="Expire le" :value="$account->expires_at?->format('Y-m-d')" :id="'exp-'.$account->id" />
                                        <x-form.input name="password" type="text" label="Nouveau mot de passe" minlength="8" maxlength="72" autocomplete="off" :id="'pw-'.$account->id" help="Vide : inchangé." />
                                        <x-form.checkbox name="regenerate" label="Générer un nouveau mot de passe" />
                                        <x-form.checkbox name="is_enabled" label="Compte actif" :checked="$account->is_enabled" />
                                        <button class="btn-primary btn-sm">Enregistrer</button>
                                    </form>
                                </td>
                                <td>
                                    @if (! $account->is_enabled)<span class="badge-slate">Désactivé</span>
                                    @elseif ($account->isExpired())<span class="badge-amber">Expiré</span>
                                    @else<span class="badge-green">Actif</span>@endif
                                </td>
                                <td class="hidden text-sm text-slate-600 md:table-cell">{{ $account->expires_at?->format('d/m/Y') ?? 'Jamais' }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <button type="button" @click="edit = ! edit" class="rounded-lg p-2 text-slate-400 hover:bg-primary-50 hover:text-primary-600" title="Modifier"><x-icon name="pencil" class="h-4 w-4" /></button>
                                    <x-admin.delete-button :action="route('admin.sandbox.accounts.destroy', $account)" confirm="Supprimer ce compte ? Ses sessions seront fermées." />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.sandbox.accounts.store') }}" class="space-y-4 rounded-xl border border-dashed border-slate-300 p-4">
            @csrf
            <p class="text-sm font-semibold text-slate-700">Nouveau compte</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.input name="username" label="Identifiant" required maxlength="32" placeholder="guest" autocomplete="off" />
                <x-form.input name="label" label="Description" maxlength="150" placeholder="Ex. : Recruteur Société X" />
                <x-form.input name="expires_at" type="date" label="Expire le" help="Facultatif." />
            </div>
            <x-form.input name="password" type="text" label="Mot de passe" minlength="8" maxlength="72" autocomplete="off" help="Vide : généré automatiquement et affiché une fois." />
            <x-form.checkbox name="is_guest" label="Compte invité (mot de passe affiché publiquement sur la page Machine SSH)" />
            <div class="flex justify-end"><button class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Créer le compte</button></div>
        </form>
    </x-admin.section>

    @if ($status)
        <x-admin.section title="Sessions ouvertes">
            @forelse ($status['sessions'] as $session)
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span><span class="font-mono font-medium">{{ $session['username'] }}</span> · {{ $session['tty'] }} · depuis {{ gmdate($session['seconds'] >= 3600 ? 'G \h i' : 'i \m\i\n', $session['seconds']) }}</span>
                    <form method="POST" action="{{ route('admin.sandbox.sessions.kill') }}">@csrf
                        <input type="hidden" name="username" value="{{ $session['username'] }}">
                        <button class="text-xs font-medium text-red-600 hover:underline">Fermer</button></form>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucune session ouverte.</p>
            @endforelse
        </x-admin.section>

        <x-admin.section title="Connexions récentes" description="Journal de l'agent (relais SSH et terminal web), remis à zéro à son redémarrage.">
            @if (empty($status['connections']))
                <p class="text-sm text-slate-500">Aucune connexion.</p>
            @else
                <div class="max-h-72 overflow-y-auto">
                    <table class="admin-table">
                        <thead><tr><th>Date</th><th>Adresse IP</th><th>Canal</th><th>Résultat</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($status['connections'] as $connection)
                                <tr>
                                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($connection['at'])->timezone(config('app.timezone'))->format('d/m H:i:s') }}</td>
                                    <td class="font-mono text-xs"><a href="{{ route('admin.ip-history', ['ip' => $connection['ip']]) }}" class="hover:underline">{{ $connection['ip'] }}</a></td>
                                    <td>{{ $connection['channel'] === 'web' ? 'Terminal web' : 'SSH' }}@if (! empty($connection['username'])) · {{ $connection['username'] }}@endif</td>
                                    <td>{{ $connection['result'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.section>
    @endif

    @if ($backups !== null)
        <x-admin.section title="Sauvegardes" description="Archives des dossiers personnels (/home). Restaurer ferme les sessions et remplace tous les dossiers.">
            <form method="POST" action="{{ route('admin.sandbox.backups.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <x-form.input name="label" label="Libellé" maxlength="120" placeholder="Ex. : Avant la démo" class="min-w-[16rem] flex-1" />
                <button class="btn-primary"><x-icon name="archive" class="h-4 w-4" /> Sauvegarder maintenant</button>
            </form>
            @if ($backups)
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead><tr><th>Sauvegarde</th><th class="hidden md:table-cell">Taille</th><th></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($backups as $backup)
                                <tr>
                                    <td>
                                        <p class="font-medium text-slate-900">{{ $backup['label'] }} @if ($backup['automatic'])<span class="badge-slate">Auto</span>@endif</p>
                                        <p class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($backup['created_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                                    </td>
                                    <td class="hidden text-sm text-slate-600 md:table-cell">{{ $mb($backup['size']) }}</td>
                                    <td class="whitespace-nowrap text-right">
                                        <form method="POST" action="{{ route('admin.sandbox.backups.restore', $backup['id']) }}" class="inline">@csrf
                                            <button class="text-xs font-medium text-primary-600 hover:underline" onclick="return confirm('Restaurer cette sauvegarde ? Les dossiers actuels seront remplacés et les sessions fermées.')">Restaurer</button></form>
                                        <a href="{{ route('admin.sandbox.backups.download', $backup['id']) }}" class="ml-2 text-xs font-medium text-slate-600 hover:underline">Télécharger</a>
                                        <x-admin.delete-button :action="route('admin.sandbox.backups.destroy', $backup['id'])" confirm="Supprimer définitivement cette sauvegarde ?" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500">Aucune sauvegarde.</p>
            @endif
        </x-admin.section>

        <x-admin.section title="Réinitialisation">
            <form method="POST" action="{{ route('admin.sandbox.reset') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <x-form.input name="confirm" label="Vider tous les dossiers personnels : tapez REINITIALISER" autocomplete="off" class="min-w-[18rem] flex-1" />
                <button class="btn-danger">Réinitialiser</button>
            </form>
        </x-admin.section>
    @endif
</x-app-layout>
