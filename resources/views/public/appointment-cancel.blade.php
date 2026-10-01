<x-public-layout title="Annulation de rendez-vous">
    @push('head')<meta name="robots" content="noindex, nofollow">@endpush
    <div class="mx-auto max-w-lg px-4 py-16 sm:px-6">
        <div class="card space-y-4 p-6 text-center">
            <h1 class="font-display text-xl font-bold text-slate-900">Rendez-vous du {{ $appointment->starts_at->translatedFormat('l j F Y à H:i') }}</h1>
            @if ($appointment->status === 'cancelled')
                <p class="text-slate-600">Ce rendez-vous est annulé. Merci d'avoir prévenu !</p>
            @elseif ($appointment->canBeCancelled())
                <p class="text-slate-600">Statut : <strong>{{ $appointment->statusLabel() }}</strong>. Voulez-vous l'annuler ? Le créneau sera libéré.</p>
                <form method="POST" action="{{ route('appointments.cancel', request()->route('token')) }}">
                    @csrf
                    <button class="btn-danger">Annuler le rendez-vous</button>
                </form>
            @else
                <p class="text-slate-600">Ce rendez-vous ne peut plus être annulé (statut : {{ strtolower($appointment->statusLabel()) }}{{ $appointment->isUpcoming() ? '' : ', date passée' }}).</p>
            @endif
        </div>
    </div>
</x-public-layout>
