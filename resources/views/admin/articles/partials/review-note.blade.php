{{-- Commentaire de l'administrateur après un renvoi en brouillon --}}
@if ($article->review_status === \App\Models\Article::REVIEW_CHANGES_REQUESTED && $article->review_note)
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
        <p class="font-semibold">À retravailler — commentaire de {{ $article->reviewer?->name ?? 'l\'administrateur' }}
            @if ($article->reviewed_at)<span class="font-normal text-red-700">({{ $article->reviewed_at->translatedFormat('j F Y') }})</span>@endif
        </p>
        <p class="mt-1 whitespace-pre-line">{{ $article->review_note }}</p>
    </div>
@endif
