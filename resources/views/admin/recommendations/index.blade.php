<x-app-layout>
    <x-slot name="title">Recommandations</x-slot>
    <x-slot name="header">Recommandations</x-slot>

    <p class="text-sm text-slate-500">
        Créez une invitation pour chaque personne sollicitée (ancien tuteur, enseignant, collègue…) et transmettez-lui le lien :
        elle rédige sa recommandation, que vous relisez puis publiez. Les recommandations « mises en avant » apparaissent aussi sur l'accueil.
        @unless (\App\Models\Page::findByKey('recommandations')?->is_public)
            <a href="{{ route('admin.pages.index') }}" class="font-medium text-primary-600 hover:underline">La page « Recommandations » est privée</a> : rendez-la publique pour les afficher.
        @endunless
    </p>

    @can('panel', 'recommendations.write')
        <form method="POST" action="{{ route('admin.recommendations.store') }}">
            @csrf
            <x-admin.section title="Nouvelle invitation">
                <div class="grid gap-5 sm:grid-cols-[1fr_200px]">
                    <x-form.input name="label" label="Personne invitée" required maxlength="150" placeholder="Ex. : M. Martin, tuteur de stage chez Société X" />
                    <x-form.input name="token_expires_at" type="date" label="Lien valable jusqu'au" help="Facultatif." />
                </div>
                <div class="flex justify-end"><button type="submit" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Créer le lien</button></div>
            </x-admin.section>
        </form>
    @endcan

    <nav class="inline-flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-white p-1 text-sm" aria-label="Filtres">
        @foreach (['' => 'Toutes'] + array_map(fn ($s) => $s[0], \App\Models\Recommendation::STATUSES) as $key => $label)
            <a href="{{ route('admin.recommendations.index', array_filter(['statut' => $key])) }}"
               @class(['rounded-md px-3 py-1.5 font-medium', 'bg-primary-50 text-primary-700' => (string) $status === (string) $key, 'text-slate-600 hover:bg-slate-50' => (string) $status !== (string) $key])>
                {{ $label }}
                @if ($key === \App\Models\Recommendation::PENDING && $pendingCount)
                    <span class="ml-1 rounded-full bg-amber-400 px-1.5 text-xs font-semibold text-amber-950">{{ $pendingCount }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    @if ($recommendations->isEmpty())
        <x-empty-state icon="badge" message="Aucune recommandation pour l'instant." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Recommandation</th><th>Statut</th><th class="hidden md:table-cell">Lien d'invitation</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($recommendations as $recommendation)
                        <tr>
                            <td>
                                <p class="font-medium text-slate-900">{{ $recommendation->author_name ?: $recommendation->label }}
                                    @if ($recommendation->is_featured)<span class="badge-amber">Accueil</span>@endif
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ $recommendation->author_name ? $recommendation->label : 'En attente de rédaction' }}
                                    @if ($recommendation->content) · {{ \Illuminate\Support\Str::limit($recommendation->content, 80) }}@endif
                                </p>
                            </td>
                            <td><span class="{{ $recommendation->statusBadge() }}">{{ $recommendation->statusLabel() }}</span></td>
                            <td class="hidden text-sm md:table-cell">
                                @if ($recommendation->invitationIsOpen())
                                    <span x-data="{ copied: false }">
                                        <button type="button" class="text-xs font-medium text-primary-600 hover:underline"
                                                @click="navigator.clipboard.writeText(@js($recommendation->inviteUrl())); copied = true; setTimeout(() => copied = false, 2000)">
                                            <span x-show="!copied">Copier le lien</span><span x-show="copied" x-cloak>Copié</span>
                                        </button>
                                    </span>
                                    <span class="block text-xs text-slate-400">{{ $recommendation->token_expires_at ? 'Jusqu\'au '.$recommendation->token_expires_at->format('d/m/Y') : 'Sans expiration' }}</span>
                                @else
                                    <span class="text-xs text-slate-400">{{ $recommendation->token_expires_at?->isPast() ? 'Expiré' : 'Fermé' }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <x-admin.edit-link can="recommendations.write" :href="route('admin.recommendations.edit', $recommendation)" />
                                <x-admin.delete-button can="recommendations.delete" :action="route('admin.recommendations.destroy', $recommendation)" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
