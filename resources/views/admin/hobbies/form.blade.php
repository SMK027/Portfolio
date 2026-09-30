@php $editing = $hobby->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le loisir' : 'Nouveau loisir' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier le loisir' : 'Nouveau loisir' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.loisirs.update', $hobby) : route('admin.loisirs.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <div class="grid gap-5 sm:grid-cols-[1fr_160px]">
                <x-form.input name="name" label="Loisir" :value="$hobby->name" required maxlength="100" />
                <x-form.input name="position" type="number" label="Ordre" :value="$hobby->position" min="0" max="999" />
            </div>
            <x-form.textarea name="description" label="Description" :value="$hobby->description" rows="4" maxlength="2000" />
            <x-form.image name="image" label="Image" :current="$hobby->imageUrl()" help="Facultative — 5 Mo max." />
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.loisirs.index')" />
    </form>
</x-app-layout>
