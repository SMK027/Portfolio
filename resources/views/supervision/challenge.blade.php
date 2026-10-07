<x-app-layout>
    <x-slot name="title">Validation superviseur</x-slot>
    <x-slot name="header">Validation superviseur</x-slot>

    <div class="mx-auto max-w-lg">
        <x-admin.section title="Habilitation requise">
            <p class="text-sm text-slate-600">
                Votre compte n'est pas habilité pour cette opération. Un superviseur peut la valider <strong>une seule fois</strong>
                avec son identifiant et son code PIN ; elle sera alors exécutée telle que vous l'avez saisie.
            </p>
            <dl class="space-y-1 rounded-lg bg-slate-50 p-3 text-sm">
                <div><dt class="inline font-medium text-slate-700">Opération :</dt>
                    <dd class="inline text-slate-600">{{ $operations->join(' ou ') }}</dd></div>
                <div><dt class="inline font-medium text-slate-700">Requête :</dt>
                    <dd class="inline break-all font-mono text-xs text-slate-500">{{ $pending['method'] }} /{{ $pending['path'] }}</dd></div>
                @if ($pending['files'])
                    <div><dt class="inline font-medium text-slate-700">Fichiers joints :</dt> <dd class="inline text-slate-600">{{ count($pending['files']) }}, conservés pour le rejeu</dd></div>
                @endif
            </dl>

            <form method="POST" action="{{ route('supervision.store') }}" class="space-y-4">
                @csrf
                <x-form.input name="username" label="Identifiant superviseur" required autocomplete="off" autofocus maxlength="50" />
                <x-form.input name="pin" type="password" label="Code PIN" required inputmode="numeric" pattern="[0-9]{4,8}" minlength="4" maxlength="8" autocomplete="off" />
                <div class="flex flex-wrap gap-2">
                    <button class="btn-primary"><x-icon name="lock" class="h-4 w-4" /> Valider l'opération</button>
                </div>
            </form>
            <form method="POST" action="{{ route('supervision.destroy') }}">
                @csrf @method('DELETE')
                <button class="btn-ghost btn-sm">Annuler l'opération</button>
            </form>
        </x-admin.section>
    </div>
</x-app-layout>
