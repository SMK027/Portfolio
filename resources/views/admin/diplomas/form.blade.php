@php $editing = $diploma->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le diplôme' : 'Nouveau diplôme' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier le diplôme' : 'Nouveau diplôme' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.diplomes.update', $diploma) : route('admin.diplomes.store') }}" class="space-y-6"
          x-data="{ precision: @js(old('date_precision', $diploma->datePrecision())) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <x-form.input name="title" label="Intitulé du diplôme" :value="$diploma->title" required />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="institution" label="Établissement" :value="$diploma->institution" required />
                <x-form.date-precision :value="$diploma->datePrecision()" class="sm:col-span-2" />
                <x-form.precise-date name="obtained_at" label="Obtention" :model="$diploma" field="obtained_at" required />
                <x-form.input name="level" label="Niveau" :value="$diploma->level" placeholder="Ex. : Bac+2, niveau 5" />
                <x-form.input name="mention" label="Mention" :value="$diploma->mention" placeholder="Ex. : Bien" />
            </div>
            <x-form.textarea name="description" label="Description" :value="$diploma->description" rows="4" />
            <x-form.input name="position" type="number" label="Ordre d'affichage" :value="$diploma->position" min="0" max="999" class="sm:w-40" help="0 = automatique (du plus récent au plus ancien)." />
        </x-admin.section>

        <x-admin.form-actions can="diplomas.write" :cancel="route('admin.diplomes.index')" />
    </form>
</x-app-layout>
