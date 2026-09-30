<x-mail::message>
@if ($event === \App\Mail\ArticleReviewNotification::SUBMITTED)
# Un article attend votre validation

**{{ $article->author?->name }}** a soumis l'article « {{ $article->title }} ».

<x-mail::button :url="route('admin.articles.show', $article)">
Relire l'article
</x-mail::button>
@elseif ($event === \App\Mail\ArticleReviewNotification::APPROVED)
# Votre article est publié

L'article « {{ $article->title }} » a été validé par {{ $article->reviewer?->name ?? 'un administrateur' }}.

<x-mail::button :url="route('admin.articles.show', $article)">
Voir l'article
</x-mail::button>
@else
# Votre article est à retravailler

L'article « {{ $article->title }} » a été renvoyé en brouillon par {{ $article->reviewer?->name ?? 'un administrateur' }}.

@if ($article->review_note)
<x-mail::panel>
{{ $article->review_note }}
</x-mail::panel>
@endif

<x-mail::button :url="route('admin.articles.edit', $article)">
Modifier l'article
</x-mail::button>
@endif
</x-mail::message>
