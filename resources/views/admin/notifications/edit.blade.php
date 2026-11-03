<x-app-layout>
    <x-slot name="title">Notifications</x-slot>
    <x-slot name="header">Notifications Discord</x-slot>

    <p class="text-sm text-slate-500">
        Recevez les événements importants du site dans un salon Discord. Dans Discord : <em>Paramètres du salon → Intégrations → Webhooks → Nouveau webhook</em>,
        puis « Copier l'URL du webhook ». L'URL est chiffrée en base et n'est plus affichée en entier une fois enregistrée.
    </p>

    <form method="POST" action="{{ route('admin.notifications.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-admin.section title="Webhook">
            <div class="flex items-start gap-3">
                <span @class(['flex h-10 w-10 flex-none items-center justify-center rounded-xl', 'bg-emerald-50 text-emerald-600' => $configured, 'bg-slate-100 text-slate-500' => ! $configured])>
                    <x-icon name="megaphone" class="h-5 w-5" />
                </span>
                <div>
                    <p class="font-medium text-slate-900">{{ $configured ? 'Webhook enregistré' : 'Aucun webhook' }}</p>
                    <p class="text-sm text-slate-500">{{ $configured ? 'Adresse se terminant par '.$webhookHint : 'Aucune notification n\'est envoyée.' }}</p>
                </div>
            </div>
            <x-form.input name="webhook" type="password" :label="$configured ? 'Remplacer l\'URL du webhook' : 'URL du webhook'" autocomplete="off" spellcheck="false"
                          placeholder="https://discord.com/api/webhooks/…" :help="$configured ? 'Laissez vide pour conserver l\'adresse actuelle.' : null" />
            @if ($configured)
                <x-form.checkbox name="forget" label="Supprimer le webhook (désactive toutes les notifications)" />
            @endif
        </x-admin.section>

        <x-admin.section title="Événements notifiés">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (\App\Services\DiscordNotifier::EVENTS as $key => [$label])
                    <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="events[]" value="{{ $key }}" @checked(in_array($key, old('events', $events), true))
                               class="mt-0.5 rounded border-slate-300 text-primary-600 shadow-sm focus:ring-primary-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('events')<p class="form-error">{{ $message }}</p>@enderror
        </x-admin.section>

        <x-admin.form-actions />
    </form>

    @if ($configured)
        <form method="POST" action="{{ route('admin.notifications.test') }}">
            @csrf
            <button type="submit" class="btn-secondary"><x-icon name="megaphone" class="h-4 w-4" /> Envoyer un message de test</button>
        </form>
    @endif
</x-app-layout>
