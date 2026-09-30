@php $editing = $experience->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier l\'expérience' : 'Nouvelle expérience' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier l\'expérience' : 'Nouvelle expérience' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.experiences.update', $experience) : route('admin.experiences.store') }}" class="space-y-6"
          x-data="{ precision: @js(old('date_precision', $experience->datePrecision())), ongoing: @js((bool) old('ongoing', $experience->exists && ! $experience->end_date)) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="title" label="Poste" :value="$experience->title" required placeholder="Ex. : Développeur web" />
                <x-form.input name="company" label="Entreprise / organisme" :value="$experience->company" required />
                <x-form.input name="location" label="Lieu" :value="$experience->location" />
                <x-form.input name="contract_type" label="Type de contrat" :value="$experience->contract_type" list="contract-types" help="Choisissez une suggestion ou saisissez librement." />
                <datalist id="contract-types">
                    @foreach (\App\Models\Experience::CONTRACT_TYPES as $type)<option value="{{ $type }}">@endforeach
                </datalist>
                <x-form.date-precision :value="$experience->datePrecision()" class="sm:col-span-2" />
                <x-form.precise-date name="start_date" label="Début" :model="$experience" field="start_date" required />
                <div>
                    <x-form.precise-date name="end_date" label="Fin" :model="$experience" field="end_date" x-show="!ongoing" />
                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="ongoing" value="0">
                        <input type="checkbox" name="ongoing" value="1" x-model="ongoing" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        Poste actuel
                    </label>
                </div>
            </div>
            <x-form.editor name="description" label="Missions et réalisations" :value="$experience->description" :mode="$experience->description_editor" :markdown="$experience->description_markdown" with-markdown placeholder="Missions, projets menés, résultats…" />
            <x-form.input name="position" type="number" label="Ordre d'affichage" :value="$experience->position" min="0" max="999" class="sm:w-40" help="0 = automatique (du plus récent au plus ancien)." />
        </x-admin.section>

        <x-admin.form-actions can="experiences.write" :cancel="route('admin.experiences.index')" />
    </form>
</x-app-layout>
