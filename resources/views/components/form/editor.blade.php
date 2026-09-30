{{--
    Texte enrichi : éditeur visuel (Editor.js) ou Markdown, au choix.
    Champs envoyés : {name} (JSON Editor.js), {name}_editor (blocks|markdown), {name}_markdown.
    Le contenu est converti à chaque changement d'éditeur et affiché de la même façon sur le site.
--}}
@props(['name', 'label' => null, 'value' => null, 'help' => null, 'placeholder' => 'Commencez à écrire…', 'mode' => 'blocks', 'markdown' => null, 'withMarkdown' => false])
@php
    $id = 'editor-'.$name;
    $json = old($name, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
    $currentMode = $withMarkdown ? old($name.'_editor', $mode ?: 'blocks') : 'blocks';
    $currentMarkdown = old($name.'_markdown', $markdown);
@endphp
<div {{ $attributes->only('class') }} data-rich-editor
     x-data="richTextEditor({ mode: @js($currentMode), toMarkdownUrl: @js(route('admin.editor.to-markdown')), toBlocksUrl: @js(route('admin.editor.to-blocks')) })"
     :data-mode="mode">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        @if ($label)<span class="form-label mb-0">{{ $label }}</span>@else<span></span>@endif
        @if ($withMarkdown)
        <div class="inline-flex rounded-lg border border-slate-300 bg-slate-50 p-0.5 text-sm" role="tablist" aria-label="Choix de l'éditeur">
            <button type="button" role="tab" :aria-selected="(mode === 'blocks').toString()" @click="switchTo('blocks')" :disabled="loading"
                    :class="mode === 'blocks' ? 'bg-white text-primary-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    class="rounded-md px-3 py-1 font-medium transition">Éditeur visuel</button>
            <button type="button" role="tab" :aria-selected="(mode === 'markdown').toString()" @click="switchTo('markdown')" :disabled="loading"
                    :class="mode === 'markdown' ? 'bg-white text-primary-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    class="rounded-md px-3 py-1 font-medium transition">Markdown</button>
        </div>
        @endif
    </div>

    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $json }}">
    @if ($withMarkdown)
        <input type="hidden" name="{{ $name }}_editor" :value="mode" value="{{ $currentMode }}">
    @endif

    <p x-show="loading" x-cloak class="mb-2 text-sm text-slate-500">Conversion en cours…</p>
    <p x-show="error" x-cloak x-text="error" class="form-error mb-2"></p>

    {{-- Éditeur visuel --}}
    <div x-show="mode === 'blocks'" @if ($currentMode !== 'blocks') x-cloak @endif>
        <div x-ref="holder" class="editorjs-holder editor-content" data-editorjs data-input="{{ $id }}"
             data-upload-url="{{ route('admin.uploads.image') }}" data-upload-by-url="{{ route('admin.uploads.image-url') }}" data-placeholder="{{ $placeholder }}"></div>
        <p class="form-help">
            {{ $help ?? 'Cliquez sur « + » pour ajouter un bloc (titre, liste, image, code, tableau…). Sélectionnez du texte pour le mettre en forme (gras, couleur, surlignage, lien…).' }}
        </p>
    </div>

    {{-- Markdown --}}
    @if ($withMarkdown)
    <div x-show="mode === 'markdown'" @if ($currentMode !== 'markdown') x-cloak @endif class="grid gap-4 lg:grid-cols-2">
        <div>
            <div class="flex flex-wrap gap-1 rounded-t-lg border border-b-0 border-slate-300 bg-slate-50 px-2 py-1.5 text-sm">
                @foreach ([
                    ['**', '**', 'Gras', '<b>G</b>'],
                    ['*', '*', 'Italique', '<i>I</i>'],
                    ["\n## ", '', 'Titre', 'H2'],
                    ["\n### ", '', 'Sous-titre', 'H3'],
                    ['[', '](https://)', 'Lien', 'Lien'],
                    ["\n- ", '', 'Liste à puces', '• Liste'],
                    ["\n1. ", '', 'Liste numérotée', '1. Liste'],
                    ["\n> ", '', 'Citation', '❝'],
                    ['`', '`', 'Code en ligne', '&lt;/&gt;'],
                    ["\n```\n", "\n```\n", 'Bloc de code', '{ }'],
                ] as [$before, $after, $title, $text])
                    <button type="button" @click="wrap(@js($before), @js($after))" title="{{ $title }}" aria-label="{{ $title }}"
                            class="rounded px-2 py-0.5 text-slate-600 hover:bg-white hover:text-slate-900">{!! $text !!}</button>
                @endforeach
            </div>
            <textarea x-ref="markdown" name="{{ $name }}_markdown" x-model="markdown" @input="schedulePreview()" rows="22" spellcheck="true"
                      class="form-input rounded-t-none font-mono text-[13px] leading-relaxed" placeholder="# Titre&#10;&#10;Votre texte en **Markdown**…">{{ $currentMarkdown }}</textarea>
            <p class="form-help">
                Syntaxe Markdown (GitHub) : titres <code>#</code>, <code>**gras**</code>, <code>*italique*</code>, listes, <code>- [ ]</code> tâches, tableaux, <code>```</code> code, <code>![légende](url)</code> images.
                Les couleurs, alignements et options d'image de l'éditeur visuel apparaissent en HTML : ils sont conservés.
            </p>
        </div>
        <div>
            <p class="form-label">Aperçu</p>
            <div class="editor-content min-h-[300px] rounded-lg border border-slate-200 bg-white p-4 sm:p-6" x-html="previewHtml"></div>
        </div>
    </div>
    @else
        <textarea x-ref="markdown" class="hidden" aria-hidden="true"></textarea>
    @endif

    @error($name)<p class="form-error">{{ $message }}</p>@enderror
    @error($name.'_markdown')<p class="form-error">{{ $message }}</p>@enderror
</div>
