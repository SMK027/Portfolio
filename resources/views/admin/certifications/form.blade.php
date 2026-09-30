@php $editing = $certification->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier la certification' : 'Nouvelle certification' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier la certification' : 'Nouvelle certification' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.certifications.update', $certification) : route('admin.certifications.store') }}" enctype="multipart/form-data" class="space-y-6"
          x-data="{ precision: @js(old('date_precision', $certification->datePrecision())) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Nom de la certification" :value="$certification->name" required />
                <x-form.input name="issuer" label="Organisme" :value="$certification->issuer" help="Facultatif (ex. : Pix, niveau de langue)." />
                <x-form.date-precision :value="$certification->datePrecision()" class="sm:col-span-2" />
                <x-form.precise-date name="issued_at" label="Obtention" :model="$certification" field="issued_at" required />
                <x-form.precise-date name="expires_at" label="Expiration" :model="$certification" field="expires_at" help="Facultatif." />
                <x-form.input name="credential_id" label="Identifiant" :value="$certification->credential_id" />
                <x-form.input name="credential_url" type="url" label="Lien de vérification" :value="$certification->credential_url" placeholder="https://…" />
            </div>
            <x-form.image name="badge" label="Badge / logo" :current="$certification->badgeUrl()" help="2 Mo max." />
            <x-form.textarea name="description" label="Description" :value="$certification->description" rows="4" />
            <x-form.input name="position" type="number" label="Ordre d'affichage" :value="$certification->position" min="0" max="999" class="sm:w-40" />
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.certifications.index')" />
    </form>
</x-app-layout>
