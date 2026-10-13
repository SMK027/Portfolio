@php
    $editing = $contact->exists;
    $methods = \App\Models\InternshipContact::METHODS;
@endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le contact' : 'Nouveau contact' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier le contact' : 'Nouveau contact' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.internship.update', $contact) : route('admin.internship.store') }}" class="space-y-6"
          x-data="{ status: @js(old('status', $contact->status)) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section title="Entreprise">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="company" label="Nom de l'entreprise / organisme" :value="$contact->company" required maxlength="150" />
                <x-form.input name="contact_name" label="Interlocuteur" :value="$contact->contact_name" maxlength="150" help="Facultatif." />
            </div>
            <x-form.input name="address" label="Adresse / code postal" :value="$contact->address" required maxlength="255" />
        </x-admin.section>

        <x-admin.section title="Démarche">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.input name="contacted_on" type="date" label="Date de contact" :value="$contact->contacted_on?->toDateString()" required />
                <x-form.select name="contact_method" label="Moyen de contact" :options="$methods" :value="$contact->contact_method" placeholder="—" required />
                <x-form.select name="status" label="Résultat" :options="\App\Models\InternshipContact::STATUSES" :value="$contact->status" required x-model="status" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2" x-show="['pending', 'processing'].includes(status)" x-cloak>
                <x-form.input name="follow_up_on" type="date" label="Date de relance" :value="$contact->follow_up_on?->toDateString()" />
                <x-form.select name="follow_up_method" label="Moyen de relance" :options="$methods" :value="$contact->follow_up_method" placeholder="—" />
            </div>
            <x-form.textarea name="notes" label="Notes" :value="$contact->notes" rows="5" maxlength="5000" />
        </x-admin.section>

        <x-admin.form-actions :can="$editing ? 'internship.update' : 'internship.create'" :cancel="route('admin.internship.index')" />
    </form>
</x-app-layout>
