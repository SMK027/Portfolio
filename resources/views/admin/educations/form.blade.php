@php $editing = $education->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier la formation' : 'Nouvelle formation' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier la formation' : 'Nouvelle formation' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.formations.update', $education) : route('admin.formations.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <x-form.input name="title" label="Intitulé de la formation" :value="$education->title" required />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="institution" label="Établissement" :value="$education->institution" required />
                <x-form.input name="location" label="Lieu" :value="$education->location" />
                <x-form.input name="start_date" type="date" label="Début" :value="$education->start_date?->toDateString()" required />
                <x-form.input name="end_date" type="date" label="Fin" :value="$education->end_date?->toDateString()" help="Laissez vide si la formation est en cours." />
            </div>
            <x-form.textarea name="description" label="Description" :value="$education->description" rows="5" />
            <x-form.input name="position" type="number" label="Ordre d'affichage" :value="$education->position" min="0" max="999" class="sm:w-40" help="0 = automatique (du plus récent au plus ancien)." />
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.formations.index')" />
    </form>
</x-app-layout>
