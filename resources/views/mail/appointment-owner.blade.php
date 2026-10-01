<x-mail::message>
@if ($event === 'cancelled')
# Rendez-vous annulé par le visiteur
@else
# Nouvelle demande de rendez-vous
@endif

<x-mail::panel>
**{{ ucfirst($appointment->starts_at->translatedFormat('l j F Y')) }}**, de {{ $appointment->starts_at->format('H:i') }} à {{ $appointment->ends_at->format('H:i') }}
**Avec :** {{ $appointment->name }} ({{ $appointment->email }}@if ($appointment->phone), {{ $appointment->phone }}@endif)
**Sujet :** {{ $appointment->topic }}
</x-mail::panel>

@if ($appointment->message)
{{ $appointment->message }}
@endif

@if ($event !== 'cancelled')
La demande attend votre confirmation.
@endif

<x-mail::button :url="route('admin.appointments.index')">
Voir les rendez-vous
</x-mail::button>
</x-mail::message>
