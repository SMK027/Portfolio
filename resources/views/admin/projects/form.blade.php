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

        <x-admin.section title="Fichiers" description="Images (JPG, PNG, WebP, GIF), PDF, Word, Excel, PowerPoint et LibreOffice — 20 Mo max. par fichier. Les images forment le carrousel ; choisissez-en une comme miniature.">
            @if ($editing && $project->files->isNotEmpty())
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($project->files as $file)
                        <li class="relative flex items-center gap-3 rounded-xl border p-2 transition"
                            :class="thumbnail === 'file:{{ $file->id }}' ? 'border-primary-500 bg-primary-50' : 'border-slate-200'"
                            x-data="{ del: false }" :style="del ? 'opacity:.45' : ''">
                            @if ($file->is_image)
                                <img src="{{ $file->url() }}" alt="" class="h-14 w-20 flex-none rounded-lg object-cover">
                            @else
                                <x-file-icon :kind="$file->kind()" class="!h-14 !w-14" />
                            @endif
                            <div class="min-w-0 flex-1">
                                <a href="{{ $file->downloadUrl() }}" class="block truncate text-sm font-medium text-slate-800 hover:text-primary-600" title="{{ $file->original_name }}">{{ $file->original_name }}</a>
                                <p class="text-xs text-slate-400">{{ $file->humanSize() }}</p>
                                <div class="mt-1 flex flex-wrap gap-3 text-xs">
                                    @if ($file->is_image)
                                        <label class="inline-flex cursor-pointer items-center gap-1 text-slate-600">
                                            <input type="radio" value="file:{{ $file->id }}" x-model="thumbnail" class="border-slate-300 text-primary-600 focus:ring-primary-500"> Miniature
                                        </label>
                                    @endif
                                    <label class="inline-flex cursor-pointer items-center gap-1 text-red-600">
                                        <input type="checkbox" name="delete_files[]" value="{{ $file->id }}" x-model="del" class="rounded border-slate-300 text-red-600 focus:ring-red-500"> Supprimer
                                    </label>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div x-data="{ pending: [] }">
                <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 transition hover:border-primary-400 hover:bg-primary-50">
                    <x-icon name="download" class="h-8 w-8 rotate-180 text-slate-400" />
                    <span><strong class="text-primary-600">Choisir des fichiers</strong> à ajouter</span>
                    <input type="file" name="files[]" multiple class="sr-only"
                           accept=".{{ implode(',.', \App\Models\ProjectFile::allowedExtensions()) }}"
                           @change="pending = Array.from($event.target.files).map(f => ({ name: f.name, image: /\.(jpe?g|png|gif|webp)$/i.test(f.name), url: /\.(jpe?g|png|gif|webp)$/i.test(f.name) ? URL.createObjectURL(f) : null }));
                                    if (thumbnail.startsWith('new:')) thumbnail = ''">
                </label>
                <ul x-show="pending.length" x-cloak class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="(f, i) in pending" :key="i">
                        <li class="flex items-center gap-3 rounded-xl border p-2" :class="thumbnail === `new:${i}` ? 'border-primary-500 bg-primary-50' : 'border-slate-200'">
                            <template x-if="f.image"><img :src="f.url" alt="" class="h-14 w-20 flex-none rounded-lg object-cover"></template>
                            <template x-if="!f.image"><span class="flex h-14 w-14 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500"><x-icon name="document" class="h-6 w-6" /></span></template>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800" x-text="f.name"></p>
                                <p class="text-xs text-emerald-600">Nouveau</p>
                                <label x-show="f.image" class="mt-1 inline-flex cursor-pointer items-center gap-1 text-xs text-slate-600">
                                    <input type="radio" :value="`new:${i}`" x-model="thumbnail" class="border-slate-300 text-primary-600 focus:ring-primary-500"> Miniature
                                </label>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
            <button type="button" x-show="thumbnail" x-cloak @click="thumbnail = ''" class="text-xs font-medium text-slate-500 underline hover:text-slate-700">Retirer la miniature</button>
            @error('files')<p class="form-error">{{ $message }}</p>@enderror
            @foreach ($errors->get('files.*') as $messages)
                @foreach ($messages as $message)<p class="form-error">{{ $message }}</p>@endforeach
            @endforeach
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.projets.index')" />
    </form>
</x-app-layout>
