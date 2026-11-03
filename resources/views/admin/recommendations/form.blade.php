<x-app-layout>
    <x-slot name="title">Recommandation</x-slot>
    <x-slot name="header">{{ $recommendation->author_name ?: $recommendation->label }}</x-slot>

    <form method="POST" action="{{ route('admin.recommendations.update', $recommendation) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @if ($recommendation->submitted_at)
            <p class="text-sm text-slate-500">
                Rédigée le {{ $recommendation->submitted_at->format('d/m/Y à H:i') }}, avec accord de publication.
                Vous pouvez corriger une coquille ; prévenez l'auteur de toute modification de fond.
            </p>
        @endif

        <x-admin.section title="Modération">
            <div class="grid gap-5 sm:grid-cols-[1fr_1fr_120px]">
                <x-form.select name="status" label="Statut" :options="array_map(fn ($s) => $s[0], \App\Models\Recommendation::STATUSES)" :value="$recommendation->status" required />
                <x-form.input name="label" label="Personne invitée (interne)" :value="$recommendation->label" required maxlength="150" />
                <x-form.input name="position" type="number" label="Ordre" :value="$recommendation->position" min="0" max="999" />
            </div>
            <x-form.checkbox name="is_featured" label="Mettre en avant sur l'accueil" :checked="$recommendation->is_featured" help="Trois au plus sont affichées, dans l'ordre choisi." />
            @if ($recommendation->invitationIsOpen())
                <p class="text-sm text-slate-500">Lien d'invitation : <span class="break-all font-mono text-xs">{{ $recommendation->inviteUrl() }}</span></p>
            @endif
        </x-admin.section>

        <x-admin.section title="Auteur">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="author_name" label="Prénom et nom" :value="$recommendation->author_name" maxlength="120" />
                <x-form.input name="relationship" label="Relation" :value="$recommendation->relationship" maxlength="150" />
                <x-form.input name="author_role" label="Poste" :value="$recommendation->author_role" maxlength="150" />
                <x-form.input name="author_company" label="Entreprise ou établissement" :value="$recommendation->author_company" maxlength="150" />
            </div>
            <x-form.input name="author_url" type="url" label="Lien vers son profil" :value="$recommendation->author_url" maxlength="2048" />
        </x-admin.section>

        <x-admin.section title="Texte">
            <x-form.textarea name="content" label="Recommandation" :value="$recommendation->content" rows="8" maxlength="3000" />
        </x-admin.section>

        <x-admin.form-actions can="recommendations.write" :cancel="route('admin.recommendations.index')" />
    </form>
</x-app-layout>
