<x-app-layout>
    <x-slot name="title">Purger le journal</x-slot>
    <x-slot name="header">Purger le journal d'activité</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.audit.index') }}" class="btn-secondary">Retour au journal</a>
    </x-slot>

    <p class="text-sm text-slate-500">
        Supprime définitivement les entrées du journal enregistrées sur une période (journées entières, bornes incluses).
        Une trace de la purge (période, nombre d'entrées, auteur) est ajoutée au journal ; les traces de purges précédentes ne sont jamais supprimées.
    </p>

    <form method="GET" action="{{ route('admin.audit.purge') }}" class="card flex flex-wrap items-end gap-3 p-4">
        <x-form.input name="from" type="date" label="Du" :value="$period[0] ?? null ? $period[0]->toDateString() : request('from')" :max="today()->toDateString()" required />
        <x-form.input name="to" type="date" label="Au" :value="$period[1] ?? null ? $period[1]->toDateString() : request('to')" :max="today()->toDateString()" required />
        <button type="submit" class="btn-secondary">Compter les entrées</button>
    </form>

    @if ($period)
        @if ($count === 0)
            <x-empty-state icon="clock" message="Aucune entrée à supprimer sur cette période." />
        @else
            <form method="POST" action="{{ route('admin.audit.purge.destroy') }}" class="card space-y-4 border-red-200 p-5">
                @csrf
                @method('DELETE')
                <input type="hidden" name="from" value="{{ $period[0]->toDateString() }}">
                <input type="hidden" name="to" value="{{ $period[1]->toDateString() }}">

                <p class="text-sm text-slate-800">
                    <strong class="text-red-700">{{ $count }} entrée(s)</strong> du {{ $period[0]->format('d/m/Y') }} au {{ $period[1]->format('d/m/Y') }} seront supprimées définitivement.
                    <a href="{{ route('admin.audit.index', ['from' => $period[0]->toDateString(), 'to' => $period[1]->toDateString(), 'archived' => 'all']) }}" class="text-primary-600 hover:underline">Les consulter</a>
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="password" type="password" label="Votre mot de passe" required autocomplete="current-password" />
                    <x-form.input name="confirmation" label="Saisissez PURGER pour confirmer" required autocomplete="off" />
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-danger"><x-icon name="trash" class="h-4 w-4" /> Supprimer {{ $count }} entrée(s)</button>
                </div>
            </form>
        @endif
    @endif
</x-app-layout>
