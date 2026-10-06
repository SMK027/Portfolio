<x-app-layout>
    <x-slot name="title">{{ $message->subject }}</x-slot>
    <x-slot name="header">Message</x-slot>
    <x-slot name="actions">
        <form method="POST" action="{{ route('admin.messages.unread', $message) }}">
            @csrf @method('PATCH')
            <button class="btn-secondary" title="Le message réapparaît comme non lu dans la liste"><x-icon name="mail" class="h-4 w-4" /> <span class="hidden sm:inline">Marquer comme non lu</span></button>
        </form>
        <a href="{{ route('admin.messages.index') }}" class="btn-secondary"><x-icon name="arrow-left" class="h-4 w-4" /> Retour</a>
    </x-slot>

    <article class="card">
        <header class="border-b border-slate-100 p-4 sm:p-6">
            <h2 class="font-display text-xl font-semibold text-slate-900">{{ $message->subject }}</h2>
            <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div class="flex gap-2"><dt class="text-slate-500">De :</dt><dd class="font-medium text-slate-800">{{ $message->fullName() }}</dd></div>
                <div class="flex gap-2"><dt class="text-slate-500">E-mail :</dt><dd><a href="mailto:{{ $message->email }}" class="font-medium text-primary-600">{{ $message->email }}</a></dd></div>
                <div class="flex gap-2"><dt class="text-slate-500">Reçu le :</dt><dd>{{ $message->created_at->translatedFormat('j F Y à H:i') }}</dd></div>
                <div class="flex gap-2"><dt class="text-slate-500">Consentement :</dt><dd class="text-emerald-700">Oui, le {{ $message->consented_at->format('d/m/Y H:i') }}</dd></div>
                <div class="flex gap-2"><dt class="text-slate-500">Notification :</dt>
                    <dd>
                        @if ($message->notified_at)
                            <span class="text-emerald-700">envoyée par e-mail</span>
                        @elseif ($message->notificationPending())
                            <span class="text-slate-600">envoi en cours</span>
                        @else
                            <span class="text-amber-700">non envoyée (problème d'envoi d'e-mails)</span>
                        @endif
                    </dd>
                </div>
                @if ($message->recaptcha_score !== null)
                    <div class="flex gap-2"><dt class="text-slate-500">Score reCAPTCHA :</dt><dd>{{ number_format($message->recaptcha_score, 1) }}</dd></div>
                @endif
            </dl>
        </header>
        <div class="whitespace-pre-line p-4 text-sm leading-relaxed text-slate-700 sm:p-6">{{ $message->message }}</div>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 sm:px-6">
            <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.$message->subject) }}" class="btn-primary"><x-icon name="mail" class="h-4 w-4" /> Répondre</a>
            @can('panel', 'messages.delete')
            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" onsubmit="return confirm('Supprimer ce message ?')">
                @csrf @method('DELETE')
                <button class="btn-danger"><x-icon name="trash" class="h-4 w-4" /> Supprimer</button>
            </form>
            @endcan
        </footer>
    </article>
</x-app-layout>
