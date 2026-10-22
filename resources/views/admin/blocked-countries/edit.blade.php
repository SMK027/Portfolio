<x-app-layout>
    <x-slot name="title">Pays bloqués</x-slot>
    <x-slot name="header">Pays bloqués</x-slot>

    <form method="POST" action="{{ route('admin.blocked-countries.update') }}" class="space-y-6"
          x-data="{ q: '', only: false, selected: @js(old('countries', $blocked)) }">
        @csrf
        @method('PUT')

        <p class="text-sm text-slate-500">
            Les visiteurs dont l'adresse IP est localisée dans un pays coché reçoivent une erreur 403 sur tout le site, API comprise.
            Utile pour limiter les connexions par VPN, les bots et les attaques automatisées venant de régions dont vous n'attendez pas de visiteurs.
            Les adresses non localisées et celles de la liste blanche (<code class="rounded bg-slate-100 px-1">LOGIN_BAN_WHITELIST</code>) ne sont jamais bloquées.
            Votre pays actuel : <strong>{{ \App\Services\GeoIp::label($myCountry) }}</strong>.
        </p>

        @unless ($geoipReady)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Base de géolocalisation absente : aucun pays ne peut être détecté (sauf en-tête <code>CF-IPCountry</code>) et le blocage reste sans effet.
            </div>
        @endunless

        <x-admin.section>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <input type="search" x-model="q" placeholder="Rechercher un pays…" class="form-input sm:w-72" aria-label="Rechercher un pays">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" x-model="only" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"> Pays bloqués uniquement
                </label>
                <span class="text-sm text-slate-500 sm:ml-auto"><span x-text="selected.length"></span> pays bloqué(s)</span>
            </div>
            @error('countries')<p class="form-error">{{ $message }}</p>@enderror
            @error('countries.*')<p class="form-error">{{ $message }}</p>@enderror

            <div class="grid max-h-[28rem] grid-cols-1 gap-1 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($available as $code => $label)
                    <label class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-50"
                           x-show="(! only || selected.includes('{{ $code }}')) && @js(mb_strtolower($label.' '.$code)).includes(q.toLowerCase())">
                        <input type="checkbox" name="countries[]" value="{{ $code }}" x-model="selected" @disabled($code === $myCountry)
                               class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                        <span>{{ $label }}</span>
                        <span class="font-mono text-xs text-slate-400">{{ $code }}</span>
                        @if ($code === $myCountry)<span class="text-xs text-slate-400">(votre pays)</span>@endif
                    </label>
                @endforeach
            </div>
        </x-admin.section>

        <p class="text-sm text-slate-500">
            Accès perdu ? Sur le serveur : <code class="rounded bg-slate-100 px-1">php artisan portfolio:blocked-countries --reset</code> vide la liste.
        </p>

        <x-admin.form-actions />
    </form>
</x-app-layout>
