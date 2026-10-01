@php
    $editing = $article->exists;
    $status = old('status', $article->published_at ? 'published' : 'draft');
    $canPublish = auth()->user()->can('publish', \App\Models\Article::class);
    $canChangeAuthor = auth()->user()->can('changeAuthor', $article);
@endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier l\'article' : 'Nouvel article' }}</x-slot>
    <x-slot name="header">{{ $editing ? $article->title : 'Nouvel article' }}</x-slot>
    @if ($editing)
        <x-slot name="actions">
            <a href="{{ route('admin.articles.show', $article) }}" class="btn-secondary"><x-icon name="eye" class="h-4 w-4" /> Consulter</a>
            <a href="{{ route('admin.articles.revisions.index', $article) }}" class="btn-secondary hidden sm:inline-flex"><x-icon name="clock" class="h-4 w-4" /> Historique</a>
            @unless ($article->isPublished())
                <a href="{{ route('admin.articles.show', $article) }}#relecture" class="btn-secondary hidden sm:inline-flex"><x-icon name="link" class="h-4 w-4" /> Faire relire</a>
            @endunless
            @if ($article->isPublished())
                <a href="{{ route('articles.show', $article) }}" target="_blank" class="btn-secondary hidden sm:inline-flex"><x-icon name="external" class="h-4 w-4" /> Sur le site</a>
            @endif
        </x-slot>
    @endif

    @if ($editing)
        <div class="space-y-4">
            @include('admin.articles.partials.review')
            @include('admin.articles.partials.review-note')
        </div>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.articles.update', $article) : route('admin.articles.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <div class="min-w-0 space-y-6">
                <x-admin.section>
                    <x-form.input name="title" label="Titre" :value="$article->title" required />
                    <x-form.textarea name="excerpt" label="Résumé" :value="$article->excerpt" rows="2" maxlength="500" help="Affiché sur les cartes d'articles (500 caractères max.)." />
                </x-admin.section>

                <x-admin.section title="Contenu">
                    <x-form.editor name="content" :value="$article->content" :mode="$article->content_editor" :markdown="$article->content_markdown" with-markdown placeholder="Rédigez votre article…" />
                </x-admin.section>

                <x-admin.section title="Galerie et documents" description="Les images s'affichent en carrousel sous l'en-tête de l'article (« Insérer dans le texte » les place aussi dans le contenu), les documents sont proposés en téléchargement à la fin. Images (JPG, PNG, WebP, GIF), PDF, Word, Excel, PowerPoint, LibreOffice et ZIP — 20 Mo max. par fichier.">
                    <x-admin.attachments :files="$editing ? $article->files : collect()" :file-class="\App\Models\ArticleFile::class" insertable />
                </x-admin.section>
            </div>

            <div class="space-y-6">
                <x-admin.section title="Publication">
                    @if ($canPublish)
                        <div x-data="{ status: @js($status) }" class="space-y-4">
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (['draft' => 'Brouillon', 'published' => 'Publié'] as $value => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="status" value="{{ $value }}" x-model="status" class="peer sr-only">
                                        <span class="block rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-600 peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:text-primary-700">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div x-show="status === 'published'" x-cloak>
                                <x-form.input name="published_at" type="datetime-local" label="Date de publication" :value="$article->published_at?->format('Y-m-d\TH:i')" help="Vide = maintenant. Une date future programme la publication." />
                            </div>
                        </div>
                        <x-form.checkbox name="is_pinned" label="Épingler en haut de la veille" :checked="$article->is_pinned" />
                    @else
                        <p><x-article-status :article="$article" /></p>
                        <p class="text-sm text-slate-600">
                            @if ($article->published_at)
                                Cet article est publié : vos modifications sont visibles dès l'enregistrement.
                            @elseif ($article->isPendingReview())
                                Un administrateur va relire l'article. Vous pouvez encore le modifier, ou retirer la demande en l'enregistrant comme brouillon.
                            @else
                                Enregistrez votre brouillon autant de fois que nécessaire, puis soumettez-le : un administrateur le validera avant publication.
                            @endif
                        </p>
                    @endif
                </x-admin.section>

                <x-admin.section title="Auteurs">
                    @if ($canChangeAuthor)
                        <x-form.autocomplete name="author_id" label="Auteur principal" :options="$authors" :selected="[$article->author_id ?? auth()->id()]"
                                             channel="article-author" required placeholder="Nom, identifiant ou e-mail…"
                                             :help="auth()->user()->isContributor() ? 'Si vous confiez l\'article à un autre auteur, vous restez co-auteur.' : 'Tout compte actif : personne, bot ou compte de service.'" />
                    @else
                        <div>
                            <span class="form-label">Auteur principal</span>
                            <p class="text-sm text-slate-800">{{ $article->author?->name ?? auth()->user()->name }}</p>
                        </div>
                    @endif
                    <x-form.autocomplete name="coauthors" label="Co-auteurs" multiple :options="collect($coauthors)->reject(fn ($o) => $o['id'] === ($article->author_id ?? auth()->id()))"
                                         :selected="$article->coauthors->pluck('id')" listen="article-author" placeholder="Ajouter un co-auteur…"
                                         help="Les co-auteurs peuvent modifier l'article. Un contributeur n'a accès qu'à la rédaction d'articles." />
                </x-admin.section>

                <x-admin.section title="Thèmes">
                    @if ($canPublish)
                        <x-form.chips name="themes" :options="$themes->pluck('name', 'id')" :selected="$article->themes->pluck('id')"
                                      :create-url="auth()->user()->can('panel', 'themes.write') ? route('admin.themes.quick') : null" create-label="Nouveau thème" />
                    @else
                        <x-form.chips name="themes" :options="$themes->pluck('name', 'id')" :selected="$article->themes->pluck('id')" empty="Aucun thème disponible." />
                    @endif
                </x-admin.section>

                <x-admin.section title="Miniature">
                    <x-form.image name="thumbnail" :current="$article->thumbnailUrl()" help="Utilisée sur les cartes et en en-tête de l'article." />
                </x-admin.section>
            </div>
        </div>

        <div class="mt-6">
            @if ($canPublish || $article->published_at)
                <x-admin.form-actions :cancel="route('admin.articles.index')" />
            @else
                <div class="sticky bottom-0 -mx-4 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
                    <a href="{{ route('admin.articles.index') }}" class="btn-secondary">Annuler</a>
                    <button type="submit" name="intent" value="draft" class="btn-secondary">Enregistrer le brouillon</button>
                    <button type="submit" name="intent" value="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" />
                        {{ $article->isPendingReview() ? 'Enregistrer (reste soumis)' : 'Soumettre pour validation' }}
                    </button>
                </div>
            @endif
        </div>
    </form>
</x-app-layout>
