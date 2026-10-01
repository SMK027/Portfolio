@php
    $editable = auth()->user()->can('panel', 'appointments.write');
    $calendarUrls = [
        'events'  => route('admin.appointments.availability.events'),
        'store'   => route('admin.appointments.availability.ranges.store'),
        'range'   => str_replace('/__KIND__/0', '/__KIND__/__ID__', route('admin.appointments.availability.ranges.update', ['kind' => '__KIND__', 'id' => 0])),
        'closure' => route('admin.appointments.availability.closures.toggle'),
    ];
@endphp
<x-app-layout>
    <x-slot name="title">Disponibilités</x-slot>
    <x-slot name="header">Disponibilités pour les rendez-vous</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.appointments.index') }}" class="btn-secondary"><x-icon name="arrow-left" class="h-4 w-4" /> Rendez-vous</a></x-slot>

    {{-- Calendrier --}}
    <section class="card space-y-4 p-4 sm:p-6"
             x-data="{ choice: null, notice: null, timer: null, appointment: null, deciding: null }"
             @availability-choose.window="choice = $event.detail"
             @appointment-open.window="appointment = $event.detail; deciding = null"
             @availability-notice.window="notice = $event.detail; clearTimeout(timer); timer = setTimeout(() => notice = null, 3500)">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl text-sm text-slate-600">
                @if ($editable)
                    <p><strong>Glissez sur la grille</strong> pour ajouter une plage, puis choisissez « chaque semaine » ou « ce jour uniquement ».
                        <strong>Déplacez ou étirez</strong> une plage pour la modifier, <strong>cliquez</strong> dessus pour la supprimer.
                        <strong>Cliquez sur la date</strong> en haut d'une colonne pour fermer ou rouvrir la journée (congés…).</p>
                @else
                    <p>Consultation seule : ce compte n'est pas autorisé à modifier les disponibilités.</p>
                @endif
            </div>
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-primary-500"></span> Chaque semaine</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-accent-500"></span> Ce jour uniquement</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-amber-500"></span> RDV en attente</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-500"></span> RDV confirmé</li>
                <li class="flex items-center gap-1.5"><span class="availability-closed-swatch h-3 w-3 rounded"></span> Jour fermé</li>
            </ul>
        </div>

        <div data-availability-calendar data-editable="{{ $editable ? '1' : '0' }}" class="availability-calendar"
             data-urls="{{ json_encode($calendarUrls) }}"></div>

        {{-- Nouvelle plage : récurrente ou ponctuelle --}}
        <div x-show="choice" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @keydown.escape.window="choice = null">
            <div @click.outside="choice = null" class="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="choice-title">
                <h2 id="choice-title" class="font-display text-lg font-semibold text-slate-900">Nouvelle disponibilité</h2>
                <p class="text-sm text-slate-600 first-letter:uppercase" x-text="choice?.label"></p>
                <div class="grid gap-2">
                    <button type="button" class="btn-primary justify-start" @click="$dispatch('availability-create', { kind: 'weekly', start: choice.start, end: choice.end }); choice = null">
                        <x-icon name="arrows-updown" class="h-4 w-4" /> <span>Chaque <span x-text="choice?.weekday"></span></span>
                    </button>
                    <button type="button" class="btn-secondary justify-start" @click="$dispatch('availability-create', { kind: 'date', start: choice.start, end: choice.end }); choice = null">
                        <x-icon name="calendar" class="h-4 w-4" /> Ce jour uniquement
                    </button>
                    <button type="button" class="btn-ghost" @click="choice = null">Annuler</button>
                </div>
            </div>
        </div>

        {{-- Rendez-vous cliqué dans le calendrier : détails et décision --}}
        <div x-show="appointment" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @keydown.escape.window="appointment = null">
            <div @click.outside="appointment = null" class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="appointment-title">
                <div class="flex items-start justify-between gap-3">
                    <h2 id="appointment-title" class="font-display text-lg font-semibold text-slate-900" x-text="appointment?.when"></h2>
                    <span :class="appointment?.status === 'confirmed' ? 'badge-green' : 'badge-amber'" x-text="appointment?.statusLabel"></span>
                </div>
                <dl class="space-y-1 text-sm text-slate-700">
                    <div><dt class="inline font-medium">Avec :</dt> <dd class="inline" x-text="appointment?.name"></dd></div>
                    <div><dt class="inline font-medium">E-mail :</dt> <dd class="inline"><a :href="'mailto:' + appointment?.email" class="text-primary-600 hover:underline" x-text="appointment?.email"></a></dd></div>
                    <div x-show="appointment?.phone"><dt class="inline font-medium">Téléphone :</dt> <dd class="inline" x-text="appointment?.phone"></dd></div>
                    <div><dt class="inline font-medium">Sujet :</dt> <dd class="inline" x-text="appointment?.topic"></dd></div>
                </dl>
                <p x-show="appointment?.message" class="whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-700" x-text="appointment?.message"></p>

                @if ($editable)
                    <template x-if="appointment?.upcoming">
                        <div class="space-y-3 border-t border-slate-100 pt-4">
                            <div x-show="! deciding" class="flex flex-wrap gap-2">
                                <button type="button" x-show="appointment?.status === 'pending'" @click="deciding = 'confirmed'" class="btn-primary"><x-icon name="check" class="h-4 w-4" /> Confirmer</button>
                                <button type="button" @click="deciding = 'declined'" class="btn-secondary text-red-600" x-text="appointment?.status === 'pending' ? 'Refuser' : 'Annuler le rendez-vous'"></button>
                            </div>
                            <form x-show="deciding" method="POST" :action="appointment?.decideUrl" class="space-y-3">
                                @csrf @method('PUT')
                                <input type="hidden" name="decision" :value="deciding">
                                <label for="calendar-note" class="form-label" x-text="deciding === 'confirmed' ? 'Message pour le visiteur (ex. : lien de visioconférence)' : 'Message pour le visiteur (facultatif)'"></label>
                                <textarea id="calendar-note" name="admin_note" rows="2" maxlength="2000" class="form-input"></textarea>
                                <div class="flex gap-2">
                                    <button class="btn-primary" x-text="deciding === 'confirmed' ? 'Confirmer et prévenir' : 'Refuser et prévenir'"></button>
                                    <button type="button" @click="deciding = null" class="btn-ghost">Retour</button>
                                </div>
                            </form>
                        </div>
                    </template>
                @endif
                <div class="text-right"><button type="button" @click="appointment = null" class="btn-ghost btn-sm">Fermer</button></div>
            </div>
        </div>

        <div x-show="notice" x-cloak x-transition role="status"
             :class="notice?.type === 'success' ? 'bg-slate-900 text-white' : 'bg-red-600 text-white'"
             class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-xl px-4 py-2.5 text-sm shadow-lg" x-text="notice?.message"></div>
    </section>

    {{-- Réglages --}}
    <form method="POST" action="{{ route('admin.appointments.availability.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <x-admin.section title="Réglages des créneaux" description="Les visiteurs voient les créneaux libres découpés dans vos plages, selon ces réglages.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.select name="duration" label="Durée d'un rendez-vous" :value="$settings['duration']" :options="[15 => '15 min', 20 => '20 min', 30 => '30 min', 45 => '45 min', 60 => '1 h', 90 => '1 h 30']" required />
                <x-form.input name="notice_hours" type="number" min="0" max="336" label="Délai de prévenance (heures)" :value="$settings['notice_hours']" required help="Pas de réservation moins de X heures à l'avance." />
                <x-form.input name="horizon_days" type="number" min="1" max="180" label="Réservable jusqu'à (jours)" :value="$settings['horizon_days']" required />
            </div>
            <x-form.input name="location" label="Lieu ou modalité" :value="$settings['location']" required maxlength="255" help="Affiché aux visiteurs et dans l'invitation d'agenda." />
            <x-form.input name="topics" label="Sujets proposés" :value="implode(', ', $settings['topics'])" required maxlength="500" help="Séparés par des virgules." />
        </x-admin.section>
        <x-admin.form-actions can="appointments.write" :cancel="route('admin.appointments.index')" />
    </form>
</x-app-layout>
