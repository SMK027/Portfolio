<x-public-layout title="Annulation de rendez-vous">
    @push('head')<meta name="robots" content="noindex, nofollow">@endpush
    <div class="mx-auto max-w-lg px-4 py-16 sm:px-6">
        <div class="card space-y-4 p-6 text-center">
            <h1 class="font-display text-xl font-bold text-slate-900">Rendez-vous du {{ $appointment->starts_at->translatedFormat('l j F Y à H:i') }}</h1>
            @if ($appointment->status === 'cancelled')
                <p class="text-slate-600">Ce rendez-vous est annulé. Merci d'avoir prévenu !</p>
            @elseif ($appointment->canBeCancelled())
                <p class="text-slate-600">Statut : <strong>{{ $appointment->statusLabel() }}</strong>. Voulez-vous l'annuler ? Le créneau sera libéré.</p>
                @if (session('success'))
                    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</p>
                @endif
                <form method="POST" action="{{ route('appointments.cancel', request()->route('token')) }}">
                    @csrf
                    <button class="btn-danger">Annuler le rendez-vous</button>
                </form>

                <div class="border-t border-slate-100 pt-4 text-left">
                    <h2 class="font-medium text-slate-900">… ou le déplacer</h2>
                    @if ($days->isEmpty())
                        <p class="mt-1 text-sm text-slate-500">Aucun autre créneau disponible pour le moment.</p>
                    @else
                        <form method="POST" action="{{ route('appointments.reschedule', request()->route('token')) }}" class="mt-2 space-y-3">
                            @csrf
                            <select name="slot" class="form-input" required aria-label="Nouveau créneau">
                                <option value="">Choisir un nouveau créneau…</option>
                                @foreach ($days as $date => $slots)
                                    <optgroup label="{{ ucfirst(\Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F')) }}">
                                        @foreach ($slots as $start)
                                            <option value="{{ $start->format('Y-m-d H:i') }}" @selected(old('slot') === $start->format('Y-m-d H:i'))>{{ $start->format('H:i') }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('slot')<p class="form-error">{{ $message }}</p>@enderror
                            <p class="text-xs text-slate-500">Le nouveau créneau devra être confirmé de nouveau ; l'ancien est libéré.</p>
                            <button class="btn-secondary w-full">Déplacer le rendez-vous</button>
                        </form>
                    @endif
                </div>
            @else
                <p class="text-slate-600">Ce rendez-vous ne peut plus être annulé (statut : {{ strtolower($appointment->statusLabel()) }}{{ $appointment->isUpcoming() ? '' : ', date passée' }}).</p>
            @endif
        </div>
    </div>
</x-public-layout>
