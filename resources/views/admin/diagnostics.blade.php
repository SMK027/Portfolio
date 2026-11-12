<x-app-layout>
    <x-slot name="title">Diagnostic</x-slot>
    <x-slot name="header">Diagnostic en temps réel</x-slot>

    <div x-data="diagnostics(@js(route('admin.diagnostics.data')), @js($initial))" class="space-y-6">
        {{-- État global et rafraîchissement --}}
        <section class="card flex flex-wrap items-center gap-4 p-4">
            <span class="h-3 w-3 flex-none rounded-full" :class="dot(state.overall)" aria-hidden="true"></span>
            <p class="flex-1 text-sm font-semibold text-slate-900" x-text="{ ok: 'Tout fonctionne', warning: 'Points à surveiller', error: 'Problème détecté' }[state.overall]"></p>
            <p class="text-xs text-slate-500" aria-live="polite">
                <span x-show="failed" x-cloak class="font-medium text-red-600">Serveur injoignable · </span>
                Mis à jour <span x-text="ago"></span>
            </p>
            <label class="flex items-center gap-2 text-xs text-slate-600">
                Toutes les
                <select x-model.number="interval" class="form-input w-auto py-1 text-xs">
                    <option value="5">5 s</option><option value="15">15 s</option><option value="60">60 s</option>
                </select>
            </label>
            <button type="button" class="btn-secondary btn-sm" @click="paused = ! paused" x-text="paused ? 'Reprendre' : 'Pause'"></button>
            <button type="button" class="btn-secondary btn-sm" @click="refresh()" :disabled="loading"><x-icon name="arrows-updown" class="h-4 w-4" /> Actualiser</button>
        </section>

        {{-- Vérifications par groupe --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <template x-for="(checks, group) in state.groups" :key="group">
                <section class="card overflow-hidden">
                    <h2 class="border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="group"></h2>
                    <ul class="divide-y divide-slate-100">
                        <template x-for="check in checks" :key="check.label">
                            <li class="flex items-start gap-3 px-4 py-3">
                                <span class="mt-1.5 h-2.5 w-2.5 flex-none rounded-full" :class="dot(check.status)" :title="check.status"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <p class="text-sm font-medium text-slate-900" x-text="check.label"></p>
                                        <p class="text-sm tabular-nums" :class="check.status === 'ok' ? 'text-slate-600' : (check.status === 'error' ? 'font-semibold text-red-600' : 'font-medium text-amber-700')" x-text="check.value"></p>
                                    </div>
                                    <p x-show="check.detail" class="mt-0.5 break-words text-xs text-slate-500" x-text="check.detail"></p>
                                </div>
                            </li>
                        </template>
                    </ul>
                </section>
            </template>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Système --}}
            <section class="card overflow-hidden">
                <h2 class="border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Système</h2>
                <dl class="divide-y divide-slate-100 text-sm">
                    <template x-for="(value, label) in state.system" :key="label">
                        <div class="flex justify-between gap-3 px-4 py-2.5">
                            <dt class="text-slate-500" x-text="label"></dt>
                            <dd class="text-right tabular-nums text-slate-800" x-text="value"></dd>
                        </div>
                    </template>
                </dl>
            </section>

            {{-- Dernières erreurs du journal Laravel --}}
            <section class="card overflow-hidden lg:col-span-2">
                <h2 class="border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Dernières erreurs (storage/logs)</h2>
                <p x-show="! state.errors.length" class="px-4 py-6 text-center text-sm text-slate-500">Aucune erreur récente dans le journal.</p>
                <ul class="divide-y divide-slate-100">
                    <template x-for="error in state.errors" :key="error.at + error.message">
                        <li class="px-4 py-2.5 text-xs">
                            <p class="flex gap-2 text-slate-500"><span class="tabular-nums" x-text="error.at"></span><span class="font-semibold text-red-600" x-text="error.level"></span></p>
                            <p class="mt-0.5 break-words font-mono text-slate-700" x-text="error.message"></p>
                        </li>
                    </template>
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
