<x-mail::message>
@if ($event === \App\Mail\StaffDeactivationMail::WARNING)
# Votre accès arrive à échéance

Bonjour {{ $account->name }}, votre compte sur {{ config('app.name') }} sera **désactivé le {{ $account->deactivates_at->translatedFormat('l j F Y à H:i') }}** ({{ $account->deactivates_at->diffForHumans() }}).

Après cette date, vous ne pourrez plus vous connecter. Si vous avez besoin d'un accès plus long, contactez un administrateur avant l'échéance.

<x-mail::button :url="route('dashboard')">
Accéder au panel
</x-mail::button>
@else
# Compte désactivé

Le compte du personnel **{{ $account->name }}** ({{ $account->email }}) a été désactivé automatiquement le {{ $account->deactivates_at->translatedFormat('j F Y à H:i') }}, comme prévu.

Pour le réactiver, cochez « Compte actif » et videz ou repoussez la date de désactivation.

<x-mail::button :url="route('admin.utilisateurs.edit', $account)">
Voir le compte
</x-mail::button>
@endif

{{ config('app.name') }}
</x-mail::message>
