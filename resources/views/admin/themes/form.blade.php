@php $editing = $theme->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le thème' : 'Nouveau thème' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier le thème' : 'Nouveau thème' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.themes.update', $theme) : route('admin.themes.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <div class="grid gap-5 sm:grid-cols-[1fr_160px]">
                <x-form.input name="name" label="Intitulé" :value="$theme->name" required maxlength="100" />
                <x-form.input name="position" type="number" label="Ordre" :value="$theme->position" min="0" max="999" />
            </div>
            <x-form.textarea name="description" label="Description" :value="$theme->description" rows="3" maxlength="500" help="Affichée en en-tête de la page du thème." />
            <x-form.image name="background" label="Image de fond" :current="$theme->backgroundUrl()" help="Format paysage recommandé (ex. 1600×900) — 8 Mo max." />
        </x-admin.section>

        <x-admin.form-actions can="themes.write" :cancel="route('admin.themes.index')" />
    </form>
</x-app-layout>
