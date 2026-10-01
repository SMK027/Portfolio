<x-app-layout>
    <x-slot name="title">Rendez-vous</x-slot>
    <x-slot name="header">Rendez-vous</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.appointments.availability') }}" class="btn-secondary"><x-icon name="clock" class="h-4 w-4" /> Disponibilités</a>
        <a href="{{ route('appointments.show') }}" target="_blank" class="btn-secondary hidden sm:inline-flex"><x-icon name="external" class="h-4 w-4" /> Page publique</a>
    </x-slot>

    @unless (\App\Models\Page::findByKey('rendez-vous')?->is_public)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            La page « Rendez-vous » est privée : les visiteurs ne la voient pas. Configurez vos disponibilités puis rendez-la publique dans
            <a href="{{ route('admin.pages.index') }}" class="font-semibold underline">Pages &amp; visibilité</a>.
        </div>
    @endunless

    <div class="flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-white p-1 text-sm">
        @foreach (\App\Http\Controllers\Admin\AppointmentController::FILTERS as $key => $label)
            <a href="{{ route('admin.appointments.index', ['filtre' => $key]) }}" @class(['rounded-md px-3 py-1 font-medium', 'bg-primary-50 text-primary-700' => $filter === $key, 'text-slate-500 hover:text-slate-900' => $filter !== $key])>
                {{ $label }}@if ($key === 'en-attente' && $pending) <span class="ml-1 rounded-full bg-amber-400 px-1.5 text-xs text-amber-950">{{ $pending }}</span>@endif
            </a>
        @endforeach
    </div>

    @if ($appointments->isEmpty())
        <x-empty-state icon="calendar" message="Aucun rendez-vous dans cette liste." />
    @else
        <div class="space-y-3">
            @foreach ($appointments as $appointment)
                <article class="card p-4 sm:p-5" x-data="{ deciding: null }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-display font-semibold text-slate-900">
                                {{ ucfirst($appointment->starts_at->translatedFormat('l j F Y')) }}, {{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }}
                                <span @class(['ml-1', 'badge-amber' => $appointment->status === 'pending', 'badge-green' => $appointment->status === 'confirmed', 'badge-slate' => in_array($appointment->status, ['declined', 'cancelled'])])>{{ $appointment->statusLabel() }}</span>
                            </p>
                            <p class="mt-1 text-sm text-slate-700"><strong>{{ $appointment->name }}</strong> · <a href="mailto:{{ $appointment->email }}" class="text-primary-600 hover:underline">{{ $appointment->email }}</a>@if ($appointment->phone) · {{ $appointment->phone }}@endif</p>
                            <p class="text-sm text-slate-500">Sujet : {{ $appointment->topic }} · demandé {{ $appointment->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @can('panel', 'appointments.write')
                                @if ($appointment->status === 'pending' && $appointment->isUpcoming())
                                    <button type="button" @click="deciding = 'confirmed'" class="btn-primary btn-sm"><x-icon name="check" class="h-4 w-4" /> Confirmer</button>
                                    <button type="button" @click="deciding = 'declined'" class="btn-secondary btn-sm text-red-600">Refuser</button>
                                @elseif ($appointment->status === 'confirmed' && $appointment->isUpcoming())
                                    <button type="button" @click="deciding = 'declined'" class="btn-secondary btn-sm text-red-600">Annuler</button>
                                @endif
                            @endcan
                            <x-admin.delete-button can="appointments.delete" :action="route('admin.appointments.destroy', $appointment)" confirm="Supprimer ce rendez-vous (le visiteur n'est pas prévenu) ?" />
                        </div>
                    </div>
                    @if ($appointment->message)<p class="mt-3 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $appointment->message }}</p>@endif
                    @if ($appointment->admin_note)<p class="mt-2 text-sm text-slate-500"><span class="font-medium">Votre message :</span> {{ $appointment->admin_note }}</p>@endif

                    <form x-show="deciding" x-cloak method="POST" action="{{ route('admin.appointments.decide', $appointment) }}" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                        @csrf @method('PUT')
                        <input type="hidden" name="decision" :value="deciding">
                        <label class="form-label" :for="'note-{{ $appointment->id }}'"
                               x-text="deciding === 'confirmed' ? 'Message pour le visiteur (ex. : lien de visioconférence)' : 'Message pour le visiteur (facultatif)'"></label>
                        <textarea id="note-{{ $appointment->id }}" name="admin_note" rows="2" maxlength="2000" class="form-input"></textarea>
                        <div class="flex gap-2">
                            <button class="btn-primary btn-sm" x-text="deciding === 'confirmed' ? 'Confirmer et prévenir' : 'Refuser et prévenir'"></button>
                            <button type="button" @click="deciding = null" class="btn-ghost btn-sm">Annuler</button>
                        </div>
                    </form>
                </article>
            @endforeach
        </div>
        {{ $appointments->links() }}
    @endif
</x-app-layout>
