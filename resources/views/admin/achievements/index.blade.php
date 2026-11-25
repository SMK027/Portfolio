<x-app-layout>
    <x-slot name="title">Succès</x-slot>
    <x-slot name="header">Succès</x-slot>
    <x-slot name="actions">
        <a href="{{ route('achievements.index') }}" target="_blank" class="btn-secondary"><x-icon name="eye" class="h-4 w-4" /> Page publique</a>
        @can('panel', 'achievements.write')
            <a href="{{ route('admin.succes.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouveau succès</a>
        @endcan
    </x-slot>

    <p class="text-sm text-slate-500">
        Les visiteurs débloquent des succès en explorant le site. Chaque succès s'appuie sur un déclencheur prévu dans le code
        (pages vues, téléchargement du CV, jours de visite…) et se débloque quand son seuil est atteint. Les visiteurs sont anonymes :
        un jeton aléatoire, conservé dans un cookie, relie leur progression d'une visite à l'autre.
    </p>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-4"><p class="text-xs text-slate-500">Visiteurs avec une progression</p><p class="font-display text-2xl font-bold text-slate-900">{{ $visitors }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Succès débloqués</p><p class="font-display text-2xl font-bold text-slate-900">{{ $unlocks }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Débloqués ces 7 derniers jours</p><p class="font-display text-2xl font-bold text-slate-900">{{ $lastWeek }}</p></div>
    </div>

    <form method="POST" action="{{ route('admin.succes.settings') }}" class="space-y-6">
        @csrf @method('PUT')
        <x-admin.section title="Réglages" description="Désactivés, les succès disparaissent du site (page, pied de page, palette) et plus aucune progression n'est enregistrée.">
            <x-form.checkbox name="enabled" label="Succès activés sur le site" :checked="$settings['enabled']" />
            <x-form.input name="title" label="Titre de la page" :value="$settings['title']" required maxlength="100" />
            <x-form.textarea name="intro" label="Introduction" :value="$settings['intro']" rows="2" maxlength="500" />
        </x-admin.section>
        <x-admin.form-actions can="achievements.write" />
    </form>

    @if ($achievements->isEmpty())
        <x-empty-state icon="trophy" message="Aucun succès pour l'instant." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Succès</th><th class="hidden md:table-cell">Déclencheur</th><th>Débloqué par</th><th>Statut</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($achievements as $achievement)
                        <tr>
                            <td>
                                <p class="flex items-center gap-2 font-medium text-slate-900">
                                    <x-icon :name="$achievement->icon" class="h-4 w-4 flex-none text-amber-500" /> {{ $achievement->title }}
                                    @if ($achievement->is_secret)<span class="badge-amber">Secret</span>@endif
                                </p>
                                <p class="text-xs text-slate-500">{{ $achievement->categoryLabel() }} · {{ $achievement->description }}</p>
                            </td>
                            <td class="hidden text-xs text-slate-600 md:table-cell">
                                {{ $achievement->triggerLabel() }} ≥ {{ $achievement->threshold }}
                            </td>
                            <td class="whitespace-nowrap text-sm">
                                {{ $achievement->unlocks_count }}
                                <span class="text-xs text-slate-400">({{ $visitors ? round($achievement->unlocks_count / $visitors * 100, 1) : 0 }} %)</span>
                            </td>
                            <td><span @class(['badge-green' => $achievement->is_active, 'badge-slate' => ! $achievement->is_active])>{{ $achievement->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                            <td class="whitespace-nowrap text-right">
                                <x-admin.edit-link can="achievements.write" :href="route('admin.succes.edit', $achievement)" />
                                <x-admin.delete-button can="achievements.delete" :action="route('admin.succes.destroy', $achievement)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @can('panel', 'achievements.delete')
        <form method="POST" action="{{ route('admin.succes.reset') }}" class="flex items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
              onsubmit="return confirm('Effacer la progression de tous les visiteurs ? Cette action est définitive.')">
            @csrf @method('DELETE')
            <span>Effacer la progression de tous les visiteurs (les succès sont conservés).</span>
            <button type="submit" class="btn-danger">Réinitialiser</button>
        </form>
    @endcan
</x-app-layout>
