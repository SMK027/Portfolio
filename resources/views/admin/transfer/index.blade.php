<x-app-layout>
    <x-slot name="title">Import / export</x-slot>
    <x-slot name="header">Import / export</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.transfer.example') }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> Fichier modèle</a>
    </x-slot>

    {{-- Rapport de simulation ou d'import --}}
    @if ($report !== null)
        <section class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-6
                           {{ $mode === 'preview' ? 'bg-amber-50' : 'bg-emerald-50' }}">
                <div>
                    <h2 class="font-display font-semibold text-slate-900">
                        {{ $mode === 'preview' ? 'Simulation : rien n\'a encore été enregistré' : 'Résultat de l\'import' }}
                    </h2>
                    @if ($mode === 'preview' && $pending)
                        <p class="text-sm text-slate-600">
                            Fichier « {{ $pending['name'] }} »
                            @if ($pending['download_images'])
                                — les images des articles seront téléchargées sur le site
                            @endif
                        </p>
                    @endif
                </div>
                @if ($mode === 'preview' && $pending)
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.transfer.cancel') }}">
                            @csrf @method('DELETE')
                            <button class="btn-secondary">Annuler</button>
                        </form>
                        <form method="POST" action="{{ route('admin.transfer.import') }}" x-data="{ sending: false }" @submit="sending = true">
                            @csrf
                            <button class="btn-primary" :disabled="sending">
                                <x-icon name="check" class="h-4 w-4" />
                                <span x-text="sending ? 'Import en cours…' : 'Confirmer l\'import'"></span>
                            </button>
                        </form>
                    </div>
                @endif
            </header>

            @if (empty($report))
                <p class="px-6 py-4 text-sm text-slate-500">Aucune des sections choisies n'est présente dans le fichier.</p>
            @else
                <table class="admin-table">
                    <thead><tr><th>Section</th><th>{{ $mode === 'preview' ? 'À créer' : 'Créés' }}</th><th>{{ $mode === 'preview' ? 'À mettre à jour' : 'Mis à jour' }}</th><th>Ignorés</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($report as $section => $line)
                            <tr>
                                <td class="font-medium text-slate-900">
                                    {{ $sections[$section] ?? $section }}
                                    @if (! empty($line['auto_created']))
                                        <span class="block text-xs font-normal text-slate-500">dont {{ $line['auto_created'] }} créé(s) automatiquement depuis les projets / articles</span>
                                    @endif
                                </td>
                                <td>{{ $line['created'] }}</td>
                                <td>{{ $line['updated'] }}</td>
                                <td @class(['font-semibold text-red-600' => $line['skipped'] > 0])>{{ $line['skipped'] }}</td>
                            </tr>
                            @if (! empty($line['errors']))
                                <tr class="!bg-transparent">
                                    <td colspan="4" class="pt-0">
                                        <ul class="space-y-0.5 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                            @foreach ($line['errors'] as $error)
                                                <li>• {{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Export --}}
        <form method="POST" action="{{ route('admin.transfer.export') }}" class="card flex flex-col p-4 sm:p-6">
            @csrf
            <h2 class="font-display text-base font-semibold text-slate-900">Exporter</h2>
            <p class="mt-1 text-sm text-slate-500">Télécharge le contenu au format JSON : sauvegarde, migration ou modèle pour un import.</p>
            <x-transfer-sections :sections="$sections" class="mt-4" />
            @error('sections')<p class="form-error">{{ $message }}</p>@enderror
            <div class="mt-auto pt-6">
                <button class="btn-primary"><x-icon name="download" class="h-4 w-4" /> Exporter en JSON</button>
            </div>
        </form>

        {{-- Import --}}
        <form method="POST" action="{{ route('admin.transfer.preview') }}" enctype="multipart/form-data" class="card flex flex-col p-4 sm:p-6">
            @csrf
            <h2 class="font-display text-base font-semibold text-slate-900">Importer</h2>
            <p class="mt-1 text-sm text-slate-500">
                Fichier JSON au format de l'export (voir le <a href="{{ route('admin.transfer.example') }}" class="font-medium text-primary-600 underline">fichier modèle</a>).
                Une <strong>simulation</strong> est d'abord affichée ; rien n'est enregistré avant votre confirmation.
                Un élément déjà présent (même slug, même titre…) est mis à jour et non dupliqué.
            </p>
            <x-form.input name="file" type="file" label="Fichier JSON" accept=".json,application/json" required class="mt-4" />
            <x-transfer-sections :sections="$sections" class="mt-4" />
            @error('sections')<p class="form-error">{{ $message }}</p>@enderror
            <label class="mt-4 inline-flex items-start gap-2 text-sm text-slate-700">
                <input type="hidden" name="download_images" value="0">
                <input type="checkbox" name="download_images" value="1" checked class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                <span>Télécharger sur ce site les images des articles fournis en HTML (recommandé si l'ancien site doit disparaître)</span>
            </label>
            <p class="form-help">Non inclus dans le JSON : photos, images de fond, badges et pièces jointes (à ajouter depuis les formulaires).</p>
            <div class="mt-auto pt-6">
                <button class="btn-primary"><x-icon name="eye" class="h-4 w-4" /> Simuler l'import</button>
            </div>
        </form>
    </div>
</x-app-layout>
