{{-- Lien de relecture d'un brouillon pour une personne sans compte --}}
@if (! $article->isPublished())
    @can('update', $article)
        <x-admin.section id="relecture" title="Lien de relecture" description="Un lien secret pour faire relire ce brouillon à quelqu'un qui n'a pas de compte. Il expire automatiquement et peut être révoqué à tout moment.">
            @if ($article->hasPreviewLink())
                <div x-data="{ copied: false }" class="flex flex-col gap-2 sm:flex-row">
                    <input type="text" readonly value="{{ $article->previewUrl() }}" x-ref="link" @focus="$event.target.select()" class="form-input flex-1 font-mono text-sm">
                    <button type="button" class="btn-secondary" @click="navigator.clipboard.writeText($refs.link.value); copied = true">
                        <x-icon name="link" class="h-4 w-4" /> <span x-text="copied ? 'Copié' : 'Copier'"></span>
                    </button>
                </div>
                <p class="text-sm text-slate-500">Valable jusqu'au {{ $article->preview_expires_at->translatedFormat('j F Y à H:i') }} ({{ $article->preview_expires_at->diffForHumans() }}).</p>
                <form method="POST" action="{{ route('admin.articles.preview.destroy', $article) }}">
                    @csrf @method('DELETE')
                    <button class="btn-ghost btn-sm text-red-600">Révoquer le lien</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.articles.preview.store', $article) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <select name="days" class="form-select w-auto" aria-label="Durée de validité">
                    @foreach (\App\Models\Article::PREVIEW_DURATIONS as $days => $label)
                        <option value="{{ $days }}" @selected($days === 7)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn-secondary"><x-icon name="link" class="h-4 w-4" /> {{ $article->hasPreviewLink() ? 'Générer un nouveau lien' : 'Créer un lien de relecture' }}</button>
            </form>
            @if ($article->hasPreviewLink())<p class="form-help">Générer un nouveau lien désactive le précédent.</p>@endif
        </x-admin.section>
    @endcan
@endif
