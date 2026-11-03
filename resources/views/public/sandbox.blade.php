<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-5xl space-y-8 px-4 py-12 sm:px-6">
        @unless ($open)
            <x-empty-state icon="lock" :message="'La machine est actuellement fermée au public. '.($schedule ?? 'Revenez un peu plus tard !')" />
        @else
            <div class="grid gap-6 md:grid-cols-2">
                <section class="card space-y-3 p-6">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Depuis votre terminal</h2>
                    <div x-data="{ copied: false }" class="flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-3 font-mono text-sm text-emerald-300">
                        <span class="flex-1 break-all">{{ $sshCommand }}</span>
                        <button type="button" class="text-xs text-slate-400 hover:text-white" @click="navigator.clipboard.writeText(@js($sshCommand)); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="! copied">Copier</span><span x-show="copied" x-cloak>Copié</span>
                        </button>
                    </div>
                    @if ($guest)
                        <p class="text-sm text-slate-600">Compte invité : <span class="font-mono font-semibold">{{ $guest->username }}</span>, mot de passe <span class="font-mono font-semibold">{{ $guest->guestPassword() }}</span></p>
                    @else
                        <p class="text-sm text-slate-600">Utilisez l'identifiant et le mot de passe qui vous ont été transmis.</p>
                    @endif
                </section>
                <section class="card space-y-2 p-6 text-sm text-slate-600">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Bon à savoir</h2>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Machine Alpine Linux partagée, aux ressources très limitées (¼ de processeur, 128 Mo de mémoire).</li>
                        <li>Pas de droits administrateur ; tunnels et redirections de ports désactivés.</li>
                        <li>{{ $internet ? 'Accès internet disponible.' : 'Pas d\'accès à internet.' }}</li>
                        <li>Les fichiers peuvent être effacés à tout moment (réinitialisations, restaurations).</li>
                        <li>Sessions web fermées après 15 minutes d'inactivité. Soyez respectueux des autres visiteurs.</li>
                    </ul>
                </section>
            </div>

            <section x-data="sandboxTerminal(@js(['ticketUrl' => route('sandbox.ticket'), 'username' => $guest?->username, 'password' => $guest?->guestPassword()]))" class="space-y-3">
                <h2 class="font-display text-lg font-semibold text-slate-900">Dans le navigateur</h2>
                <form x-show="state === 'idle' || state === 'closed'" @submit.prevent="connect()" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="sandbox-user" class="form-label">Identifiant</label>
                        <input id="sandbox-user" x-model="username" required autocomplete="username" autocapitalize="off" spellcheck="false" maxlength="32" class="form-input font-mono">
                    </div>
                    <div class="flex-1">
                        <label for="sandbox-password" class="form-label">Mot de passe</label>
                        <input id="sandbox-password" x-model="password" type="password" required autocomplete="current-password" maxlength="72" class="form-input font-mono">
                    </div>
                    <button type="submit" class="btn-primary"><x-icon name="terminal" class="h-4 w-4" /> Se connecter</button>
                </form>
                <p x-show="message" x-text="message" x-cloak :class="state === 'closed' ? 'text-red-600' : 'text-slate-500'" class="text-sm"></p>
                <div x-show="state === 'connecting' || state === 'connected'" x-cloak class="overflow-hidden rounded-xl bg-[#0b1020] p-2 shadow-lg">
                    <div x-ref="screen" class="h-[28rem]"></div>
                </div>
                <button x-show="state === 'connected'" x-cloak type="button" @click="disconnect()" class="btn-secondary btn-sm">Se déconnecter</button>
            </section>
        @endunless
    </div>
</x-public-layout>
