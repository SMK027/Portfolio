{{-- Éditeur de texte enrichi Editor.js ; le JSON est stocké dans un champ caché --}}
@props(['name', 'label' => null, 'value' => null, 'help' => null, 'placeholder' => 'Commencez à écrire…'])
@php
    $id = 'editor-'.$name;
    $json = old($name, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<span class="form-label">{{ $label }}</span>@endif
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $json }}">
    <div class="editorjs-holder editor-content" data-editorjs data-input="{{ $id }}"
         data-upload-url="{{ route('admin.uploads.image') }}" data-upload-by-url="{{ route('admin.uploads.image-url') }}" data-placeholder="{{ $placeholder }}"></div>
    <p class="form-help">
        {{ $help ?? 'Cliquez sur « + » pour ajouter un bloc (titre, liste, image, code, tableau…). Sélectionnez du texte pour le mettre en forme (gras, couleur, surlignage, lien…).' }}
    </p>
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</div>
