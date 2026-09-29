@php
    $editing = $article->exists;
    $status = old('status', $article->published_at ? 'published' : 'draft');
@endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier l\'article' : 'Nouvel article' }}</x-slot>
    <x-slot name="header">{{ $editing ? $article->title : 'Nouvel article' }}</x-slot>
    @if ($editing)
        <x-slot name="actions"><a href="{{ route('articles.show', $article) }}" target="_blank" class="btn-secondary"><x-icon name="eye" class="h-4 w-4" /> {{ $article->isPublished() ? 'Voir' : 'Aperçu' }}</a></x-slot>
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
                    <x-form.editor name="content" :value="$article->content" placeholder="Rédigez votre article…" />
                </x-admin.section>

                <x-admin.section title="Galerie et documents" description="Les images s'affichent en carrousel sous l'en-tête de l'article, les documents sont proposés en téléchargement à la fin. Images (JPG, PNG, WebP, GIF), PDF, Word, Excel, PowerPoint et LibreOffice — 20 Mo max. par fichier.">
                    <x-admin.attachments :files="$editing ? $article->files : collect()" :file-class="\App\Models\ArticleFile::class" />
                </x-admin.section>
            </div>

            <div class="space-y-6">
                <x-admin.section title="Publication">
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
                </x-admin.section>

                <x-admin.section title="Auteurs">
                    <x-form.select name="author_id" label="Auteur principal" :value="$article->author_id" :options="$users->pluck('name', 'id')" required />
                    <x-form.chips name="coauthors" label="Co-auteurs" :options="$users->pluck('name', 'id')" :selected="$article->coauthors->pluck('id')"
                                  help="Les co-auteurs sont des comptes (menu « Comptes »). Un contributeur n'a pas accès à l'administration." />
                </x-admin.section>

                <x-admin.section title="Thèmes">
                    <x-form.chips name="themes" :options="$themes->pluck('name', 'id')" :selected="$article->themes->pluck('id')" empty="Aucun thème disponible." />
                </x-admin.section>

                <x-admin.section title="Miniature">
                    <x-form.image name="thumbnail" :current="$article->thumbnailUrl()" help="Utilisée sur les cartes et en en-tête de l'article." />
                </x-admin.section>
            </div>
        </div>

        <div class="mt-6">
            <x-admin.form-actions :cancel="route('admin.articles.index')" />
        </div>
    </form>
</x-app-layout>
