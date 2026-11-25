@php
    $editing = $achievement->exists;
    $triggers = collect(\App\Achievements\Triggers::ALL)->map(fn ($t) => $t[0].' ('.$t[3].')')->all();
    $categories = collect(\App\Achievements\Triggers::CATEGORIES)->map(fn ($c) => $c[0])->all();
    $icons = array_combine(\App\Models\Achievement::ICONS, \App\Models\Achievement::ICONS);
@endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le succès' : 'Nouveau succès' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier le succès' : 'Nouveau succès' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.succes.update', $achievement) : route('admin.succes.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section title="Présentation">
            <div class="grid gap-5 sm:grid-cols-[1fr_160px]">
                <x-form.input name="title" label="Titre" :value="$achievement->title" required maxlength="100" />
                <x-form.input name="position" type="number" label="Ordre" :value="$achievement->position" min="0" max="999" />
            </div>
            <x-form.input name="description" label="Description" :value="$achievement->description" maxlength="255" help="Ce qu'il faut faire pour le débloquer." />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="category" label="Catégorie" :options="$categories" :value="$achievement->category" required />
                <x-form.select name="icon" label="Icône" :options="$icons" :value="$achievement->icon" required />
            </div>
        </x-admin.section>

        <x-admin.section title="Déblocage" description="Le succès se débloque quand le compteur du déclencheur atteint le seuil. Un succès ajouté ou abaissé se débloque à la prochaine action concernée du visiteur.">
            <div class="grid gap-5 sm:grid-cols-[1fr_160px]">
                <x-form.select name="trigger" label="Déclencheur" :options="$triggers" :value="$achievement->trigger" required />
                <x-form.input name="threshold" type="number" label="Seuil" :value="$achievement->threshold" min="1" max="100000" required />
            </div>
            <x-form.checkbox name="is_secret" label="Secret : titre et description masqués tant qu'il n'est pas débloqué" :checked="$achievement->is_secret" />
            <x-form.checkbox name="is_active" label="Actif" :checked="$achievement->is_active" />
            @if ($editing)
                <p class="text-sm text-slate-500">Débloqué par {{ $achievement->unlocks_count }} visiteur{{ $achievement->unlocks_count > 1 ? 's' : '' }}.</p>
            @endif
        </x-admin.section>

        <x-admin.form-actions can="achievements.write" :cancel="route('admin.succes.index')" />
    </form>
</x-app-layout>
