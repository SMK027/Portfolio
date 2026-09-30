<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * Droits sur les articles de veille.
 * - Administrateurs : tout.
 * - Contributeurs : consultent tous les articles, créent des brouillons,
 *   modifient ceux dont ils sont auteur ou co-auteur et les soumettent à
 *   validation. Ils ne publient, n'épinglent et ne suppriment rien.
 * - Bots : selon leurs autorisations (articles.read / write / publish / delete).
 */
class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canWriteArticles();
    }

    public function view(User $user, Article $article): bool
    {
        return $user->canWriteArticles();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isContributor() || $user->hasBotPermission('articles.write');
    }

    public function update(User $user, Article $article): bool
    {
        return $user->isAdmin()
            || ($user->isContributor() && $article->isAuthoredBy($user))
            || $user->hasBotPermission('articles.write');
    }

    /**
     * Changer l'auteur principal (et les co-auteurs) : administrateurs, bots
     * pouvant écrire (articles.write), contributeur auteur principal de l'article.
     */
    public function changeAuthor(User $user, ?Article $article = null): bool
    {
        return $user->isAdmin()
            || $user->hasBotPermission('articles.write')
            || ($user->isContributor() && (! $article?->exists || $article->author_id === $user->id));
    }

    /** Publier, programmer, épingler. */
    public function publish(User $user, ?Article $article = null): bool
    {
        return $user->isAdmin() || $user->hasBotPermission('articles.publish');
    }

    /** Soumettre à validation (ou retirer la demande). */
    public function submit(User $user, Article $article): bool
    {
        return $article->published_at === null
            && (($user->isContributor() && $article->isAuthoredBy($user)) || $user->hasBotPermission('articles.write'));
    }

    /** Valider ou renvoyer en brouillon un article soumis. */
    public function review(User $user, Article $article): bool
    {
        return $user->isAdmin() || $user->hasBotPermission('articles.publish');
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->isAdmin() || $user->hasBotPermission('articles.delete');
    }
}
