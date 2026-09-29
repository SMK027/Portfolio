@php $editing = $skill->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier la compétence' : 'Nouvelle compétence' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier la compétence' : 'Nouvelle compétence' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.competences.update', $skill) : route('admin.competences.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Nom" :value="$skill->name" required placeholder="Ex. : Laravel" />
                <x-form.input name="category" label="Catégorie" :value="$skill->category" list="skill-categories" placeholder="Ex. : Back-end" help="Choisissez une catégorie existante ou saisissez-en une nouvelle." />
                <datalist id="skill-categories">
                    @foreach ($categories as $category)<option value="{{ $category }}">@endforeach
                </datalist>
                <x-form.select name="level" label="Niveau" :value="$skill->level" placeholder="Non précisé" :options="\App\Models\Skill::LEVEL_LABELS" />
                <x-form.input name="position" type="number" label="Ordre d'affichage" :value="$skill->position" min="0" max="999" />
            </div>
            <x-form.textarea name="description" label="Description" :value="$skill->description" rows="2" maxlength="500" />
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.competences.index')" />
    </form>
</x-app-layout>
