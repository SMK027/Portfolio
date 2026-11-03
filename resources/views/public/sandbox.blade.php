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

            <section id="terminal" x-data="sandboxTerminal(@js(['ticketUrl' => route('sandbox.ticket'), 'username' => $guest?->username, 'password' => $guest?->guestPassword()]))" class="space-y-3">
                <h2 class="font-display text-lg font-semibold text-slate-900">Dans le navigateur</h2>
                <div x-show="state === 'idle' || state === 'closed'" class="card space-y-4 p-4">
                    <form @submit.prevent="connect()" class="flex flex-col gap-3 sm:flex-row sm:items-end">
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
                    @if ($ephemeral)
                        <div class="flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-slate-600">Pas de compte ? Ouvrez une <strong>session jetable</strong> : un dossier temporaire, effacé à la déconnexion.</p>
                            <button type="button" @click="connect(true)" class="btn-secondary flex-none">Session jetable</button>
                        </div>
                    @endif
                </div>
                <p x-show="message" x-text="message" x-cloak :class="state === 'closed' ? 'text-red-600' : 'text-slate-500'" class="text-sm"></p>

                <div x-show="state === 'connecting' || state === 'connected'" x-cloak
                     :class="fullscreen ? 'fixed inset-0 z-50 flex flex-col rounded-none' : 'rounded-xl shadow-lg'"
                     class="overflow-hidden bg-[#0b1020]">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 px-3 py-2 text-xs text-slate-400">
                        <span class="font-mono" x-text="sessionUser ? sessionUser + '@sandbox' : 'connexion…'"></span>
                        <span x-show="warning" x-text="warning" class="font-medium text-amber-300"></span>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="copy()" class="rounded px-2 py-1 hover:bg-white/10 hover:text-white" title="Copier la sélection (Ctrl+Maj+C)">Copier</button>
                            <button type="button" @click="paste()" class="rounded px-2 py-1 hover:bg-white/10 hover:text-white" title="Coller (Ctrl+Maj+V)">Coller</button>
                            <button type="button" @click="toggleFullscreen()" class="rounded px-2 py-1 hover:bg-white/10 hover:text-white" x-text="fullscreen ? 'Réduire' : 'Plein écran'"></button>
                            <button type="button" @click="disconnect()" class="rounded px-2 py-1 text-red-300 hover:bg-red-500/20">Quitter</button>
                        </div>
                    </div>
                    <div x-ref="screen" :class="fullscreen ? 'flex-1' : 'h-[28rem]'" class="p-2"></div>
                    {{-- Touches absentes des claviers mobiles --}}
                    <div class="flex gap-1 overflow-x-auto border-t border-white/10 px-2 py-2 sm:hidden">
                        <button type="button" @click="ctrl = ! ctrl" :class="ctrl ? 'bg-primary-500 text-white' : 'bg-white/10 text-slate-200'" class="flex-none rounded px-3 py-1.5 font-mono text-xs">Ctrl</button>
                        <template x-for="name in keys" :key="name">
                            <button type="button" @click="key(name)" class="flex-none rounded bg-white/10 px-3 py-1.5 font-mono text-xs text-slate-200" x-text="name"></button>
                        </template>
                    </div>
                </div>
            </section>
        @endunless
    </div>
</x-public-layout>
