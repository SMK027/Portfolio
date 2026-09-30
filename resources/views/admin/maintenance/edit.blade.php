<x-app-layout>
    <x-slot name="title">Maintenance</x-slot>
    <x-slot name="header">Mode maintenance</x-slot>

    <form method="POST" action="{{ route('admin.maintenance.update') }}" class="space-y-6"
          x-data="{ enabled: @js((bool) old('enabled', $active)) }">
        @csrf
        @method('PUT')
        <input type="hidden" name="enabled" :value="enabled ? 1 : 0">

        <x-admin.section>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl"
                          :class="enabled ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600'">
                        <x-icon name="wrench" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-medium text-slate-900" x-text="enabled ? 'Maintenance activée' : 'Site accessible à tous'"></p>
                        <p class="text-sm text-slate-500" x-show="!enabled">Activez la maintenance pour masquer le site aux visiteurs pendant vos modifications.</p>
                        <p class="text-sm text-slate-500" x-show="enabled" x-cloak>Les visiteurs voient une page de maintenance ; vous continuez à naviguer et à modifier le site normalement.</p>
                    </div>
                </div>
                <button type="button" role="switch" :aria-checked="enabled.toString()" @click="enabled = !enabled"
                        :class="enabled ? 'bg-amber-500' : 'bg-slate-300'"
                        class="relative inline-flex h-7 w-12 flex-none items-center rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                    <span class="sr-only">Activer la maintenance</span>
                    <span :class="enabled ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"></span>
                </button>
            </div>
            @if ($active && $endsAt)
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    Fin automatique prévue le <strong>{{ $endsAt->translatedFormat('j F Y à H:i') }}</strong> ({{ $endsAt->diffForHumans() }}).
                </p>
            @endif
        </x-admin.section>

        <div x-show="enabled" x-cloak>
            <x-admin.section title="Options (facultatives)">
                <x-form.input name="ends_at" type="datetime-local" label="Désactivation automatique"
                              :value="$endsAt?->format('Y-m-d\TH:i')" :min="now()->format('Y-m-d\TH:i')" class="sm:w-72"
                              help="Le site redevient accessible à cette date (heure de Paris). Vide = jusqu'à désactivation manuelle." />
                <x-form.textarea name="reason" label="Motif affiché aux visiteurs" :value="$reason" rows="3" maxlength="1000"
                                 placeholder="Ex. : Mise à jour de mes projets en cours, revenez bientôt !" />
            </x-admin.section>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
            <p>
                Pendant la maintenance, les pages publiques et les fichiers sont remplacés par la
                <a href="{{ route('home') }}" target="_blank" class="font-medium text-primary-600 underline">page de maintenance</a>
                (visible dans une fenêtre de navigation privée). Elle est renvoyée avec un <strong>statut HTTP 200</strong>
                pour ne pas déclencher d'alerte dans vos outils de surveillance de disponibilité.
            </p>
            <p class="mt-2">Les administrateurs connectés naviguent normalement ; la page de connexion reste accessible.</p>
        </div>

        <x-admin.form-actions can="maintenance.manage" />
    </form>
</x-app-layout>
