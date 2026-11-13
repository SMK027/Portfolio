<x-app-layout>
    <x-slot name="title">File d'attente</x-slot>
    <x-slot name="header">File d'attente</x-slot>

    <div class="space-y-6">
        <x-admin.section>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <p class="text-sm text-slate-500">État</p>
                    @if ($settings['mode'] === 'off')
                        <p class="mt-1 font-medium text-slate-900">Désactivée</p>
                    @elseif ($queueing)
                        <p class="mt-1 font-medium text-amber-700">En cours{{ $settings['mode'] === 'manual' ? ' (forcée)' : '' }}</p>
                        @if ($since)<p class="text-xs text-slate-500">Saturé depuis {{ $since->diffForHumans() }}</p>@endif
                    @else
                        <p class="mt-1 font-medium text-emerald-700">En veille — accès libre</p>
                    @endif
                </div>
                <div>
                    <p class="text-sm text-slate-500">Visiteurs sur le site</p>
                    <p class="mt-1 font-display text-2xl font-bold text-slate-900">{{ $admitted }} <span class="text-sm font-normal text-slate-500">/ {{ $settings['capacity'] }}</span></p>
                </div>
                <div>
                    <p class="text-sm text-slate-500">En attente</p>
                    <p class="mt-1 font-display text-2xl font-bold text-slate-900">{{ $waiting }}</p>
                </div>
            </div>
            @if ($waiting > 0)
                @can('panel', 'waiting_room.manage')
                    <form method="POST" action="{{ route('admin.waiting-room.admit') }}" class="mt-2">
                        @csrf
                        <button class="btn-secondary">Faire entrer tous les visiteurs en attente</button>
                    </form>
                @endcan
            @endif
        </x-admin.section>

        <form method="POST" action="{{ route('admin.waiting-room.update') }}" class="space-y-6"
              x-data="{ mode: @js(old('mode', $settings['mode'])) }">
            @csrf
            @method('PUT')

            <x-admin.section title="Fonctionnement">
                <x-form.select name="mode" label="Mode" :options="\App\Services\WaitingRoom::MODES" :value="$settings['mode']" x-model="mode" class="sm:w-72"
                               help="Automatique : la file se déclenche seule quand la capacité est atteinte et se lève dès que tout le monde est entré. Manuelle : tous les nouveaux visiteurs passent par la file." />
                <div class="grid gap-4 sm:grid-cols-2" x-show="mode !== 'off'" x-cloak>
                    <x-form.input name="capacity" type="number" min="1" max="100000" label="Visiteurs simultanés maximum" :value="$settings['capacity']" required
                                  help="Au-delà, les nouveaux visiteurs patientent." />
                    <x-form.input name="session_minutes" type="number" min="1" max="120" label="Libération d'une place après (minutes d'inactivité)" :value="$settings['session_minutes']" required />
                </div>
                <div x-show="mode === 'manual'" x-cloak>
                    <x-form.input name="ends_at" type="datetime-local" label="Retour au mode automatique"
                                  :value="$endsAt?->format('Y-m-d\TH:i')" :min="now()->format('Y-m-d\TH:i')" class="sm:w-72"
                                  help="Facultatif (heure de Paris). Vide = jusqu'à modification manuelle." />
                </div>
                <div x-show="mode !== 'off'" x-cloak>
                    <x-form.textarea name="message" label="Message affiché aux visiteurs en attente" :value="$settings['message']" rows="3" maxlength="1000"
                                     placeholder="Ex. : Merci pour votre patience, beaucoup de monde suite à la publication de mon dernier projet !" />
                </div>
            </x-admin.section>

            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                <p>Les visiteurs entrent dans l'ordre d'arrivée ; la page d'attente se met à jour seule toutes les quelques secondes et est renvoyée avec un <strong>statut HTTP 200</strong>.</p>
                <p class="mt-2">Les personnes connectées, la page de connexion et l'administration ne sont jamais concernées. Les robots d'indexation ne prennent pas de place sur le site.</p>
            </div>

            <x-admin.form-actions can="waiting_room.manage" />
        </form>
    </div>
</x-app-layout>
