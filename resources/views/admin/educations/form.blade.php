@php $editing = $education->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier la formation' : 'Nouvelle formation' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier la formation' : 'Nouvelle formation' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.formations.update', $education) : route('admin.formations.store') }}" class="space-y-6"
          x-data="{ precision: @js(old('date_precision', $education->datePrecision())), ongoing: @js((bool) old('ongoing', $education->exists && ! $education->end_date)) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <x-form.input name="title" label="Intitulé de la formation" :value="$education->title" required />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="institution" label="Établissement" :value="$education->institution" required />
                <x-form.input name="location" label="Lieu" :value="$education->location" />
                <x-form.date-precision :value="$education->datePrecision()" class="sm:col-span-2" />
                <x-form.precise-date name="start_date" label="Début" :model="$education" field="start_date" required />
                <div>
                    <x-form.precise-date name="end_date" label="Fin" :model="$education" field="end_date" x-show="!ongoing" />
                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="ongoing" value="0">
                        <input type="checkbox" name="ongoing" value="1" x-model="ongoing" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        Formation en cours
                    </label>
                </div>
            </div>
            <x-form.textarea name="description" label="Description" :value="$education->description" rows="5" />
            <x-form.input name="position" type="number" label="Ordre d'affichage" :value="$education->position" min="0" max="999" class="sm:w-40" help="0 = automatique (du plus récent au plus ancien)." />
        </x-admin.section>

        <x-admin.form-actions can="educations.write" :cancel="route('admin.formations.index')" />
    </form>
</x-app-layout>
