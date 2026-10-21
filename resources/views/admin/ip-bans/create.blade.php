<x-app-layout>
    <x-slot name="title">Bannir une adresse IP</x-slot>
    <x-slot name="header">Bannir une adresse IP</x-slot>

    <form method="POST" action="{{ route('admin.ip-bans.store') }}" class="space-y-6"
          x-data="{ permanent: @js((bool) old('permanent', false)) }">
        @csrf
        <input type="hidden" name="permanent" :value="permanent ? 1 : 0">

        <x-admin.section>
            <p class="text-sm text-slate-500">
                L'adresse n'aura plus accès à tout le site, API comprise, jusqu'à la date de fin ou à la levée du bannissement.
                Les adresses de la liste blanche ne peuvent pas être bannies. Bannir votre adresse actuelle
                (<code class="rounded bg-slate-100 px-1">{{ request()->ip() }}</code>) exige la validation d'un superviseur (identifiant + PIN)
                et vous coupe aussitôt l'accès au site ; seule la console (<code class="rounded bg-slate-100 px-1">php artisan portfolio:ip-ban {{ request()->ip() }} --lift</code>) ou un autre accès permettra de le lever.
            </p>

            <x-form.input name="ip_address" label="Adresse IP" :value="$ip" required maxlength="45" class="sm:w-72" placeholder="Ex. : 203.0.113.7 ou 2001:db8::1" autocomplete="off" spellcheck="false" />

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" x-model="permanent" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                Sans date de fin (jusqu'à levée manuelle)
            </label>

            <div x-show="!permanent" x-cloak>
                <x-form.input name="banned_until" type="datetime-local" label="Banni jusqu'au" class="sm:w-72"
                              :value="now()->addDay()->format('Y-m-d\TH:i')"
                              :min="now()->format('Y-m-d\TH:i')" help="Heure de Paris." />
            </div>

            <x-form.input name="reason" label="Motif" maxlength="255" placeholder="Ex. : aspiration du site, spam du formulaire de contact…" />
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.ip-bans.index')" label="Bannir" />
    </form>
</x-app-layout>
