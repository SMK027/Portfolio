<x-app-layout>
    <x-slot name="title">{{ $account->name }}</x-slot>
    <x-slot name="header">{{ $account->name }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.service-accounts.index') }}" class="btn-secondary hidden sm:inline-flex"><x-icon name="arrow-left" class="h-4 w-4" /> Retour</a>
        <a href="{{ route('admin.service-accounts.edit', $account) }}" class="btn-primary"><x-icon name="pencil" class="h-4 w-4" /> Autorisations</a>
    </x-slot>

    @if ($newToken)
        <section class="card border-emerald-300 bg-emerald-50 p-4 sm:p-6" x-data="{ copied: false }">
            <h2 class="font-display font-semibold text-emerald-900">Code « {{ $newToken['name'] }} » créé</h2>
            <p class="mt-1 text-sm text-emerald-900">Copiez-le maintenant : <strong>il ne sera plus jamais affiché</strong>. Seule son empreinte est conservée.</p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input type="text" readonly value="{{ $newToken['plain'] }}" x-ref="code" class="form-input flex-1 font-mono text-sm" @focus="$event.target.select()">
                <button type="button" class="btn-primary" @click="navigator.clipboard.writeText($refs.code.value); copied = true">
                    <x-icon name="check" class="h-4 w-4" /> <span x-text="copied ? 'Copié' : 'Copier'"></span>
                </button>
            </div>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <x-admin.section title="Codes d'application" description="Un code désactivé ou supprimé cesse immédiatement de fonctionner (y compris les sessions de bot ouvertes avec lui). Utilisez un code par outil ou par machine pour pouvoir les couper séparément.">
            @if ($tokens->isEmpty())
                <p class="text-sm text-slate-500">Aucun code pour l'instant.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($tokens as $token)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="font-medium {{ $token->isDisabled() ? 'text-slate-400' : 'text-slate-900' }}">{{ $token->name }}
                                    @if ($token->isDisabled())<span class="badge-slate ml-1">désactivé le {{ $token->disabled_at->format('d/m/Y') }}</span>@endif
                                </p>
                                <p class="text-xs text-slate-500">
                                    <code>{{ $token->token_prefix }}…</code> · créé {{ $token->created_at->translatedFormat('j F Y') }}@if ($token->creator) par {{ $token->creator->name }}@endif
                                    · {{ $token->last_used_at ? 'utilisé '.$token->last_used_at->diffForHumans().($token->last_used_ip ? ' ('.$token->last_used_ip.')' : '') : 'jamais utilisé' }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.service-accounts.tokens.toggle', [$account, $token]) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn-secondary btn-sm">{{ $token->isDisabled() ? 'Activer' : 'Désactiver' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.service-accounts.tokens.destroy', [$account, $token]) }}" onsubmit="return confirm('Supprimer définitivement ce code ? Les outils ou sessions qui l\'utilisent perdront immédiatement l\'accès.')">
                                    @csrf @method('DELETE')
                                    <button class="btn-secondary btn-sm text-red-600">Supprimer définitivement</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.service-accounts.tokens.store', $account) }}" class="flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-end">
                @csrf
                <x-form.input name="name" label="Intitulé du nouveau code" maxlength="100" required placeholder="Ex. : Script de publication (serveur CI)" class="flex-1" />
                <button class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Générer un code</button>
            </form>
        </x-admin.section>

        <x-admin.section title="Compte">
            <p class="text-sm">
                <span class="badge-slate">{{ $account->isBot() ? 'Bot' : 'Compte de service' }}</span>
                @if ($account->is_active)<span class="badge-green">Actif</span>@else<span class="badge bg-red-50 text-red-700">Désactivé</span>@endif
            </p>
            @if ($account->description)<p class="text-sm text-slate-600">{{ $account->description }}</p>@endif
            <div>
                <p class="form-label">Autorisations</p>
                @forelse ($account->permissions ?? [] as $permission)
                    <p class="text-sm text-slate-700">• {{ \App\Support\ServicePermissions::label($permission) }}</p>
                @empty
                    <p class="text-sm text-amber-700">Aucune : ce compte ne peut rien faire.</p>
                @endforelse
            </div>
            <a href="{{ route('admin.audit.index', ['user' => $account->id]) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-700">Voir son activité <x-icon name="arrow-right" class="h-4 w-4" /></a>
        </x-admin.section>
    </div>

    @if ($account->isBot())
        <x-admin.section title="Connexion du bot">
            <p class="text-sm text-slate-600">Le bot se connecte sur <a href="{{ route('login.bot') }}" class="font-mono text-primary-600">{{ route('login.bot') }}</a> avec l'un de ses codes actifs.
                La session est vérifiée à chaque requête : désactiver ou supprimer le code, ou désactiver le compte, la coupe immédiatement.</p>
        </x-admin.section>
    @else
        @include('admin.service-accounts.partials.api-docs')
    @endif
</x-app-layout>
