<x-app-layout>
    <x-slot name="title">Easter eggs</x-slot>
    <x-slot name="header">Easter eggs</x-slot>
    <x-slot name="actions">
        <a href="{{ route('easter-eggs.show') }}" target="_blank" class="btn-secondary"><x-icon name="eye" class="h-4 w-4" /> Aperçu</a>
        @can('panel', 'easter_eggs.write')
            <a href="{{ route('admin.easter-eggs.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouvel élément</a>
        @endcan
    </x-slot>

    <p class="text-sm text-slate-500">
        Page secrète, jamais indexée et introuvable par son adresse : seul un déclencheur caché (sur n'importe quelle page publique) délivre un lien
        valable quelques minutes. Sur la page, chaque élément peut avoir ses propres conditions d'affichage et sa propre combinaison.
    </p>

    {{-- Réglages d'accès --}}
    <form method="POST" action="{{ route('admin.easter-eggs.settings') }}" class="space-y-6">
        @csrf @method('PUT')
        <x-admin.section title="Page" description="Le titre et l'introduction s'affichent en haut de la page secrète.">
            <x-form.checkbox name="enabled" label="Page activée (déclencheurs actifs sur le site)" :checked="$settings['enabled']" />
            <x-form.input name="title" label="Titre" :value="$settings['title']" required maxlength="150" />
            <x-form.textarea name="intro" label="Introduction" :value="$settings['intro']" rows="2" maxlength="2000" />
            <x-form.input name="access_minutes" type="number" min="1" max="1440" label="Durée d'accès (minutes)" :value="$settings['access_minutes']" required
                          help="Validité du lien délivré par un déclencheur, puis de l'accès du visiteur qui l'a ouvert." />
        </x-admin.section>

        <x-admin.section title="Déclencheurs" description="Laissez un champ vide pour désactiver ce déclencheur. Les combinaisons ne sont jamais envoyées en clair au navigateur.">
            <x-form.input name="sequence" label="Séquence de touches" :value="$settings['sequence']" maxlength="255"
                          help="Noms de touches séparés par des espaces (ArrowUp, ArrowDown, ArrowLeft, ArrowRight, Enter, Escape, lettres, chiffres…). Par défaut : le code Konami." />
            <x-form.input name="word" label="Mot magique" :value="$settings['word']" maxlength="40" help="Tapé au clavier n'importe où sur le site (hors champs de saisie)." />
            <div class="grid gap-5 sm:grid-cols-[1fr_180px]">
                <x-form.input name="click_selector" label="Élément à cliquer (sélecteur CSS)" :value="$settings['click_selector']" maxlength="150"
                              placeholder="Ex. : header a[href='{{ url('/') }}']" help="Par exemple le logo ou la photo de profil." />
                <x-form.input name="click_count" type="number" min="2" max="30" label="Nombre de clics" :value="$settings['click_count']" required help="Rapprochés (moins de 1,5 s entre deux)." />
            </div>
        </x-admin.section>
        <x-admin.form-actions can="easter_eggs.write" />
    </form>

    {{-- Éléments --}}
    @if ($eggs->isEmpty())
        <x-empty-state icon="sparkles" message="Aucun élément pour l'instant." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Élément</th><th class="hidden md:table-cell">Conditions</th><th>Statut</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($eggs as $egg)
                        <tr>
                            <td>
                                <p class="flex items-center gap-2 font-medium text-slate-900">
                                    <x-icon :name="$egg->icon()" class="h-4 w-4 flex-none text-slate-400" /> {{ $egg->title }}
                                    @if ($egg->isLocked())<x-icon name="lock" class="h-3.5 w-3.5 text-amber-500" />@endif
                                </p>
                                <p class="text-xs text-slate-500">{{ $egg->typeLabel() }}@if ($egg->type === 'effect') · {{ \App\Models\EasterEgg::EFFECTS[$egg->effect] ?? $egg->effect }}@endif</p>
                            </td>
                            <td class="hidden text-xs text-slate-600 md:table-cell">{{ implode(' · ', $egg->conditionsSummary()) ?: 'Toujours visible' }}</td>
                            <td><span @class(['badge-green' => $egg->is_active, 'badge-slate' => ! $egg->is_active])>{{ $egg->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                            <td class="whitespace-nowrap text-right">
                                <x-admin.edit-link can="easter_eggs.write" :href="route('admin.easter-eggs.edit', $egg)" />
                                <x-admin.delete-button can="easter_eggs.delete" :action="route('admin.easter-eggs.destroy', $egg)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
