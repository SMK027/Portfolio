<x-app-layout>
    <x-slot name="title">Messages</x-slot>
    <x-slot name="header">Messages reçus</x-slot>
    @if (auth()->user()->isAdmin())
    <x-slot name="actions">
        <form method="POST" action="{{ route('admin.mail.test') }}">
            @csrf
            <button class="btn-secondary" title="Envoie un e-mail de test à votre adresse pour vérifier la configuration SMTP">
                <x-icon name="mail" class="h-4 w-4" /> <span class="hidden sm:inline">Tester l'envoi d'e-mails</span>
            </button>
        </form>
    </x-slot>
    @endif

    <form method="GET" role="search" class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <label for="messages-search" class="sr-only">Rechercher dans les messages</label>
        <input id="messages-search" type="search" name="q" value="{{ $search }}" maxlength="100"
               placeholder="Rechercher (nom, e-mail, objet, contenu…)" class="form-input sm:col-span-2 lg:col-span-3">
        <label for="messages-status" class="sr-only">Statut</label>
        <select id="messages-status" name="statut" class="form-input">
            <option value="">Lus et non lus</option>
            @foreach (\App\Http\Controllers\Admin\ContactMessageController::STATUSES as $key => $label)
                <option value="{{ $key }}" @selected(($filters['statut'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm text-slate-600">Du <input type="date" name="du" value="{{ $filters['du'] ?? '' }}" class="form-input"></label>
        <label class="flex items-center gap-2 text-sm text-slate-600">au <input type="date" name="au" value="{{ $filters['au'] ?? '' }}" class="form-input"></label>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6 lg:justify-end">
            <button class="btn-primary"><x-icon name="search" class="h-4 w-4" /> Filtrer</button>
            @if ($filtered)<a href="{{ route('admin.messages.index') }}" class="btn-secondary">Réinitialiser</a>@endif
        </div>
    </form>

    @if ($filtered && $messages->isNotEmpty())
        <p class="text-sm text-slate-500">{{ $messages->total() }} {{ $messages->total() > 1 ? 'messages trouvés' : 'message trouvé' }}@if ($search !== '') pour « {{ $search }} »@endif.</p>
    @endif

    @if ($messages->isEmpty())
        <x-empty-state icon="inbox" :message="$filtered ? ($search !== '' ? 'Aucun message ne correspond à « '.$search.' ».' : 'Aucun message ne correspond à ces filtres.') : 'Aucun message reçu pour le moment.'" />
    @else
        <div class="card divide-y divide-slate-100">
            @foreach ($messages as $message)
                <a href="{{ route('admin.messages.show', $message) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50">
                    <span class="mt-2 h-2 w-2 flex-none rounded-full {{ $message->read_at ? 'bg-transparent' : 'bg-primary-500' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="truncate text-sm {{ $message->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $message->fullName() }}</p>
                            <time class="flex-none text-xs text-slate-400" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('d/m/Y H:i') }}</time>
                        </div>
                        <p class="flex items-center gap-2 text-sm {{ $message->read_at ? 'text-slate-600' : 'font-medium text-slate-800' }}">
                            <span class="truncate">{{ $message->subject }}</span>
                            @unless ($message->notified_at || $message->notificationPending())
                                <span class="badge-amber flex-none" title="La notification par e-mail n'a pas pu être envoyée">E-mail non envoyé</span>
                            @endunless
                        </p>
                        <p class="truncate text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($message->message, 120) }}</p>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $messages->links() }}
    @endif
</x-app-layout>
