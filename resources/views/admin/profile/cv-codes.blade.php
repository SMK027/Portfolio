<x-app-layout>
    <x-slot name="title">Codes d'accès au CV</x-slot>
    <x-slot name="header">Codes d'accès au CV</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.profile.edit') }}" class="btn-secondary">Retour à la présentation</a>
    </x-slot>

    <p class="text-sm text-slate-500">
        Le CV public n'est remis que sur présentation d'un code. Créez un code par destinataire (candidature, recruteur…)
        et transmettez-lui le code ou le lien, qui ouvre le formulaire avec le code prérempli. Révoquer un code coupe aussitôt l'accès.
    </p>
    @unless ($profile->cvAvailable())
        <p class="badge-amber">Aucun CV téléchargeable : déposez un CV et autorisez son téléchargement dans la présentation.</p>
    @endunless

    @can('panel', 'profile.write')
        <form method="POST" action="{{ route('admin.profile.cv-codes.store') }}">
            @csrf
            <x-admin.section title="Nouveau code">
                <div class="grid gap-5 sm:grid-cols-[1fr_180px_160px]">
                    <x-form.input name="label" label="Destinataire / usage" required maxlength="150" placeholder="Ex. : Candidature Société X" />
                    <x-form.input name="expires_at" type="date" label="Expire le" help="Facultatif." />
                    <x-form.input name="max_uses" type="number" label="Utilisations max." min="1" max="10000" help="Facultatif." />
                </div>
                <div class="flex justify-end"><button type="submit" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Générer un code</button></div>
            </x-admin.section>
        </form>
    @endcan

    @if ($codes->isEmpty())
        <x-empty-state icon="lock" message="Aucun code pour l'instant : le CV n'est accessible à personne." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr><th>Destinataire</th><th>Code</th><th class="hidden md:table-cell">Utilisations</th><th class="hidden md:table-cell">Expiration</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($codes as $code)
                        <tr @class(['opacity-60' => ! $code->isUsable()])>
                            <td class="font-medium text-slate-900">
                                {{ $code->label }}
                                @if ($code->isExpired())<span class="badge-amber">Expiré</span>@elseif ($code->isExhausted())<span class="badge-amber">Épuisé</span>@endif
                            </td>
                            <td x-data="{ copied: false }" class="whitespace-nowrap">
                                <span class="font-mono font-semibold">{{ $code->formatted() }}</span>
                                <button type="button" class="ml-1 text-xs font-medium text-primary-600 hover:underline"
                                        @click="navigator.clipboard.writeText(@js($code->shareUrl())); copied = true; setTimeout(() => copied = false, 2000)">
                                    <span x-show="!copied">Copier le lien</span><span x-show="copied" x-cloak>Copié</span>
                                </button>
                            </td>
                            <td class="hidden text-sm text-slate-600 md:table-cell">
                                {{ $code->uses }}{{ $code->max_uses ? ' / '.$code->max_uses : '' }}
                                @if ($code->last_used_at)<span class="block text-xs text-slate-400">Dernière : {{ $code->last_used_at->format('d/m/Y H:i') }}</span>@endif
                            </td>
                            <td class="hidden text-sm text-slate-600 md:table-cell">{{ $code->expires_at?->format('d/m/Y') ?? 'Jamais' }}</td>
                            <td class="w-12 text-right">
                                <x-admin.delete-button can="profile.write" :action="route('admin.profile.cv-codes.destroy', $code)" label="Révoquer" confirm="Révoquer ce code ? Son destinataire ne pourra plus télécharger le CV." />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
