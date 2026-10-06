<x-mail::message>
@if ($event === 'confirmed')
# Votre rendez-vous est confirmé

Bonjour {{ $appointment->name }}, notre échange est confirmé.
@elseif (in_array($event, ['reminder_day', 'reminder_hour'], true))
# Rappel de votre rendez-vous

Bonjour {{ $appointment->name }}, je vous rappelle notre rendez-vous {{ $event === 'reminder_hour' ? 'dans moins d\'une heure' : ($appointment->starts_at->isTomorrow() ? 'de demain' : 'à venir') }}. À très bientôt !
@elseif ($event === 'reschedule')
# Votre rendez-vous doit être déplacé

Bonjour {{ $appointment->name }}, un imprévu m'empêche malheureusement de maintenir notre rendez-vous. Toutes mes excuses : merci d'en réserver un nouveau sur un autre créneau.
@elseif ($event === 'declined')
# Demande de rendez-vous

Bonjour {{ $appointment->name }}, je ne peux malheureusement pas vous recevoir sur ce créneau.
@else
# Demande reçue

Bonjour {{ $appointment->name }}, votre demande de rendez-vous est bien enregistrée. Vous recevrez un e-mail dès qu'elle sera confirmée.
@endif

<x-mail::panel>
**{{ ucfirst($appointment->starts_at->translatedFormat('l j F Y')) }}**, de {{ $appointment->starts_at->format('H:i') }} à {{ $appointment->ends_at->format('H:i') }}
**Sujet :** {{ $appointment->topic }}
@if (! in_array($event, ['declined', 'reschedule'], true))
**Lieu :** {{ $settings['location'] }}
@endif
</x-mail::panel>

@if ($appointment->admin_note)
**Message de {{ $owner }} :**

{{ $appointment->admin_note }}
@endif

@if ($event === 'confirmed')
L'invitation est jointe (fichier .ics) pour l'ajouter à votre agenda.
@endif

@if (in_array($event, ['declined', 'reschedule'], true))
<x-mail::button :url="route('appointments.show')">
{{ $event === 'reschedule' ? 'Réserver un nouveau créneau' : 'Choisir un autre créneau' }}
</x-mail::button>
@elseif ($appointment->canBeCancelled())
Un empêchement ? [Annulez le rendez-vous]({{ $appointment->cancelUrl() }}) pour libérer le créneau.
@endif

{{ $owner }}
</x-mail::message>
