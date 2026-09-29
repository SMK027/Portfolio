<x-mail::message>
# Nouveau message depuis le portfolio

**De :** {{ $contactMessage->fullName() }} ({{ $contactMessage->email }})
**Objet :** {{ $contactMessage->subject }}
**Reçu le :** {{ $contactMessage->created_at->translatedFormat('j F Y à H:i') }}

<x-mail::panel>
{{ $contactMessage->message }}
</x-mail::panel>

L'expéditeur a consenti à être recontacté. Répondez directement à cet e-mail pour lui écrire.

<x-mail::button :url="route('admin.messages.show', $contactMessage)">
Voir dans l'administration
</x-mail::button>
</x-mail::message>
