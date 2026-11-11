<x-mail::message>
# Confirmez votre adresse e-mail

Bonjour {{ $firstName }}, pour envoyer votre message depuis le formulaire de contact, saisissez ce code :

<x-mail::panel>
<div style="font-size: 28px; font-weight: bold; letter-spacing: 8px; text-align: center;">{{ $code }}</div>
</x-mail::panel>

Ce code est valable {{ $minutes }} minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e-mail : aucun message ne sera envoyé.
</x-mail::message>
