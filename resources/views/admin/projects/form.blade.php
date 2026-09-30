@php
    $editing = $project->exists;
    $links = old('links', $project->links->map(fn ($l) => ['label' => $l->label, 'url' => $l->url])->all());
    $currentThumb = old('thumbnail', $project->thumbnail_file_id ? 'file:'.$project->thumbnail_file_id : '');
@endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le projet' : 'Nouveau projet' }}</x-slot>
    <x-slot name="header">{{ $editing ? $project->title : 'Nouveau projet' }}</x-slot>
    @if ($editing)
        <x-slot name="actions"><a href="{{ route('projects.show', $project) }}" target="_blank" class="btn-secondary"><x-icon name="eye" class="h-4 w-4" /> Voir</a></x-slot>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.projets.update', $project) : route('admin.projets.store') }}" enctype="multipart/form-data"
          class="space-y-6" x-data="{ thumbnail: @js($currentThumb) }">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="thumbnail" :value="thumbnail">

        <x-admin.section title="Informations">
            <div class="grid gap-5 sm:grid-cols-[1fr_200px]">
                <x-form.input name="title" label="Titre" :value="$project->title" required />
                <x-form.input name="published_on" type="date" label="Date de création / publication" :value="$project->published_on?->toDateString()" required />
            </div>
            <x-form.textarea name="description" label="Description" :value="$project->description" rows="10" required />
        </x-admin.section>

        <x-admin.section title="Classement">
            <x-form.chips name="themes" label="Thèmes" :options="$themes->pluck('name', 'id')" :selected="$project->themes->pluck('id')" empty="Aucun thème : créez-en depuis le menu « Thèmes »." />
            <x-form.chips name="skills" label="Compétences mises en avant" :options="$skills->pluck('name', 'id')" :selected="$project->skills->pluck('id')" empty="Aucune compétence : créez-en depuis le menu « Compétences »." />
        </x-admin.section>

        <x-admin.section title="Liens" description="URL du projet en ligne, dépôts GitHub, documentation… (facultatif)">
            <div x-data="linkList(@js(array_values($links)))" class="space-y-3">
                <template x-for="(link, i) in links" :key="i">
                    <div class="flex flex-col gap-2 rounded-lg border border-slate-200 p-3 sm:flex-row sm:items-center sm:border-0 sm:p-0">
                        <input type="text" :name="`links[${i}][label]`" x-model="link.label" placeholder="Libellé (facultatif)" maxlength="100" class="form-input sm:w-56">
                        <input type="url" :name="`links[${i}][url]`" x-model="link.url" placeholder="https://github.com/…" class="form-input flex-1" required>
                        <button type="button" @click="remove(i)" class="btn-ghost self-end text-red-600 sm:self-auto" aria-label="Retirer ce lien"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                </template>
                <button type="button" @click="add()" class="btn-secondary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Ajouter un lien</button>
                @error('links.*.url')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </x-admin.section>

        <x-admin.section title="Fichiers" description="Images (JPG, PNG, WebP, GIF), PDF, Word, Excel, PowerPoint, LibreOffice et ZIP — 20 Mo max. par fichier. Les images forment le carrousel ; choisissez-en une comme miniature.">
            <x-admin.attachments :files="$editing ? $project->files : collect()" :file-class="\App\Models\ProjectFile::class" with-thumbnail />
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.projets.index')" />
    </form>
</x-app-layout>
