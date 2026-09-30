{{-- Validation d'un article soumis par un contributeur (administrateurs) --}}
@can('review', $article)
    @if ($article->isPendingReview())
        <section class="card border-amber-200 bg-amber-50/60 p-4 sm:p-6" x-data="{ refusing: {{ $errors->has('review_note') ? 'true' : 'false' }} }">
            <h2 class="flex items-center gap-2 font-display text-base font-semibold text-amber-900">
                <x-icon name="clock" class="h-5 w-5" /> En attente de validation
            </h2>
            <p class="mt-1 text-sm text-amber-900/80">
                Soumis par {{ $article->author?->name }} le {{ $article->submitted_at?->translatedFormat('j F Y à H:i') }}.
            </p>

            <form method="POST" action="{{ route('admin.articles.approve', $article) }}" class="mt-4 space-y-3" x-show="!refusing">
                @csrf
                <x-form.input name="published_at" type="datetime-local" label="Date de publication" help="Vide = maintenant. Une date future programme la publication." />
                <div class="flex flex-wrap gap-2">
                    <button class="btn-primary"><x-icon name="check" class="h-4 w-4" /> Valider et publier</button>
                    <button type="button" class="btn-secondary" @click="refusing = true">Renvoyer en brouillon…</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.articles.request-changes', $article) }}" class="mt-4 space-y-3" x-show="refusing" x-cloak>
                @csrf
                <x-form.textarea name="review_note" label="Commentaire pour l'auteur" rows="4" required maxlength="2000" placeholder="Points à corriger avant publication…" />
                <div class="flex flex-wrap gap-2">
                    <button class="btn-danger">Renvoyer en brouillon</button>
                    <button type="button" class="btn-ghost" @click="refusing = false">Annuler</button>
                </div>
            </form>
        </section>
    @endif
@endcan
