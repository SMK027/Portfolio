{{--
    Gestion des pièces jointes (images et documents) d'un projet ou d'un article.
    - files          : fichiers existants
    - fileClass      : classe du modèle de fichier (extensions autorisées)
    - withThumbnail  : permet de choisir une image comme miniature ; nécessite une
                       variable Alpine "thumbnail" déclarée sur le formulaire parent
    - insertable     : bouton « Insérer dans le texte » sur les images (éditeur du même formulaire)
--}}
@props(['files', 'fileClass', 'withThumbnail' => false, 'insertable' => false])

@if ($files->isNotEmpty())
    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($files as $file)
            <li class="relative flex items-center gap-3 rounded-xl border p-2 transition"
                @if ($withThumbnail)
                    :class="thumbnail === 'file:{{ $file->id }}' ? 'border-primary-500 bg-primary-50' : 'border-slate-200'"
                @else
                    class="border-slate-200"
                @endif
                x-data="{ del: false }" :style="del ? 'opacity:.45' : ''">
                @if ($file->is_image)
                    <img src="{{ $file->url() }}" alt="" class="h-14 w-20 flex-none rounded-lg object-cover">
                @else
                    <x-file-icon :kind="$file->kind()" class="!h-14 !w-14" />
                @endif
                <div class="min-w-0 flex-1">
                    <a href="{{ $file->downloadUrl() }}" class="block truncate text-sm font-medium text-slate-800 hover:text-primary-600" title="{{ $file->original_name }}">{{ $file->original_name }}</a>
                    <p class="text-xs text-slate-400">{{ $file->is_image ? 'Image' : strtoupper($file->extension()) }} · {{ $file->humanSize() }}</p>
                    <div class="mt-1 flex flex-wrap gap-3 text-xs">
                        @if ($insertable && $file->is_image)
                            <button type="button" class="inline-flex items-center gap-1 font-medium text-primary-600 hover:text-primary-700"
                                    title="Ajoute l'image à la position du curseur dans le contenu. Si vous supprimez ce fichier, l'image disparaîtra aussi du texte."
                                    @click="$dispatch('editor-insert-image', { url: @js(parse_url($file->url(), PHP_URL_PATH)), caption: '' })">
                                <x-icon name="plus" class="h-3.5 w-3.5" /> Insérer dans le texte
                            </button>
                        @endif
                        @if ($withThumbnail && $file->is_image)
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
               accept=".{{ implode(',.', $fileClass::allowedExtensions()) }}"
               @change="pending = Array.from($event.target.files).map(f => ({ name: f.name, image: /\.(jpe?g|png|gif|webp)$/i.test(f.name), url: /\.(jpe?g|png|gif|webp)$/i.test(f.name) ? URL.createObjectURL(f) : null }));
                        @if ($withThumbnail) if (thumbnail.startsWith('new:')) thumbnail = '' @endif">
    </label>
    <ul x-show="pending.length" x-cloak class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <template x-for="(f, i) in pending" :key="i">
            <li class="flex items-center gap-3 rounded-xl border p-2"
                @if ($withThumbnail) :class="thumbnail === `new:${i}` ? 'border-primary-500 bg-primary-50' : 'border-slate-200'" @else class="border-slate-200" @endif>
                <template x-if="f.image"><img :src="f.url" alt="" class="h-14 w-20 flex-none rounded-lg object-cover"></template>
                <template x-if="!f.image"><span class="flex h-14 w-14 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500"><x-icon name="document" class="h-6 w-6" /></span></template>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-800" x-text="f.name"></p>
                    <p class="text-xs text-emerald-600">Nouveau</p>
                    @if ($withThumbnail)
                        <label x-show="f.image" class="mt-1 inline-flex cursor-pointer items-center gap-1 text-xs text-slate-600">
                            <input type="radio" :value="`new:${i}`" x-model="thumbnail" class="border-slate-300 text-primary-600 focus:ring-primary-500"> Miniature
                        </label>
                    @endif
                </div>
            </li>
        </template>
    </ul>
</div>

@if ($withThumbnail)
    <button type="button" x-show="thumbnail" x-cloak @click="thumbnail = ''" class="text-xs font-medium text-slate-500 underline hover:text-slate-700">Retirer la miniature</button>
@endif
@error('files')<p class="form-error">{{ $message }}</p>@enderror
@foreach ($errors->get('files.*') as $messages)
    @foreach ($messages as $message)<p class="form-error">{{ $message }}</p>@endforeach
@endforeach
