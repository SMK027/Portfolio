@php use App\Mail\AccountAccessMail as M; @endphp
<x-mail::message>
@switch ($event)
@case (M::WELCOME)
# Bienvenue {{ $account->name }}

Un compte vient d'être créé pour vous sur {{ config('app.name') }}.

- **Identifiant :** {{ $account->username }}
- **Adresse e-mail :** {{ $account->email }}

Votre mot de passe vous est communiqué séparément par l'administrateur. Pensez à le changer depuis votre profil après votre première connexion.

<x-mail::button :url="route('login')">
Se connecter
</x-mail::button>
@break

@case (M::INVITATION)
# Bienvenue {{ $account->name }}

Un compte vient d'être créé pour vous sur {{ config('app.name') }} (identifiant **{{ $account->username }}**).

Pour l'activer, choisissez votre mot de passe :

<x-mail::button :url="$url">
Choisir mon mot de passe
</x-mail::button>

Ce lien est valable {{ $expiry }} et ne peut servir qu'une fois. Passé ce délai, demandez un nouveau lien à un administrateur.
@break

@case (M::RESET)
# Réinitialisation du mot de passe

Bonjour {{ $account->name }}, une réinitialisation du mot de passe de votre compte **{{ $account->username }}** a été demandée.

<x-mail::button :url="$url">
Choisir un nouveau mot de passe
</x-mail::button>

Ce lien est valable {{ $expiry }} et ne peut servir qu'une fois. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail : votre mot de passe actuel reste valable.
@break

@default
# Mot de passe modifié

Bonjour {{ $account->name }}, le mot de passe de votre compte **{{ $account->username }}** vient d'être modifié par un administrateur. Le nouveau mot de passe vous est communiqué séparément.

Si vous n'attendiez pas ce changement, contactez un administrateur.

<x-mail::button :url="route('login')">
Se connecter
</x-mail::button>
@endswitch

{{ config('app.name') }}
</x-mail::message>
