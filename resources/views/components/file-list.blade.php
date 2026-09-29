{{-- Liste de documents téléchargeables (pièces jointes d'un projet ou d'un article) --}}
@props(['documents'])
<ul {{ $attributes->merge(['class' => 'grid gap-3 sm:grid-cols-2']) }}>
    @foreach ($documents as $document)
        <li class="card flex items-center gap-3 p-3">
            <x-file-icon :kind="$document->kind()" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-800" title="{{ $document->original_name }}">{{ $document->original_name }}</p>
                <p class="text-xs uppercase text-slate-400">{{ $document->extension() }} · {{ $document->humanSize() }}</p>
            </div>
            @if ($document->isPdf())
                <a href="{{ $document->url() }}" target="_blank" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-primary-600" aria-label="Ouvrir {{ $document->original_name }}"><x-icon name="eye" class="h-5 w-5" /></a>
            @endif
            <a href="{{ $document->downloadUrl() }}" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-primary-600" aria-label="Télécharger {{ $document->original_name }}"><x-icon name="download" class="h-5 w-5" /></a>
        </li>
    @endforeach
</ul>
