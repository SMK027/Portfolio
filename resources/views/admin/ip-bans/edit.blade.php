<x-app-layout>
    <x-slot name="title">Bannissement de {{ $ban->ip_address }}</x-slot>
    <x-slot name="header">Bannissement de {{ $ban->ip_address }}</x-slot>

    <form method="POST" action="{{ route('admin.ip-bans.update', $ban) }}" class="space-y-6"
          x-data="{ permanent: @js((bool) old('permanent', $ban->banned_until === null)) }">
        @csrf
        @method('PUT')
        <input type="hidden" name="permanent" :value="permanent ? 1 : 0">

        <x-admin.section>
            <p class="text-sm text-slate-500">
                Banni depuis le {{ $ban->created_at->format('d/m/Y à H:i') }}
                @if ($ban->attempts) après {{ $ban->attempts }} échecs de connexion @endif.
            </p>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" x-model="permanent" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                Sans date de fin (jusqu'à levée manuelle)
            </label>

            <div x-show="!permanent" x-cloak>
                <x-form.input name="banned_until" type="datetime-local" label="Banni jusqu'au" class="sm:w-72"
                              :value="($ban->banned_until ?? now()->addMinutes(config('auth.login_ban.duration')))->format('Y-m-d\TH:i')"
                              :min="now()->format('Y-m-d\TH:i')" help="Heure de Paris." />
            </div>

            <x-form.input name="reason" label="Motif" :value="$ban->reason" maxlength="255" />
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.ip-bans.index')" />
    </form>
</x-app-layout>
