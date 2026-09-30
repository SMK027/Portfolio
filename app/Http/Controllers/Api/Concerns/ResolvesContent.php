<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Skill;
use App\Models\Theme;
use App\Models\User;
use App\Services\Markdown\MarkdownToEditorJs;
use App\Support\EditorContent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Conversions communes de l'API : texte enrichi (Editor.js, Markdown, HTML),
 * thèmes et compétences par nom, comptes par e-mail.
 */
trait ResolvesContent
{
    /**
     * Texte enrichi fourni en Editor.js ({field}), Markdown ({field}_markdown) ou HTML ({field}_html).
     *
     * @return array<string, mixed>|null  valeurs à enregistrer, ou null si le champ n'est pas fourni
     */
    protected function richTextInput(Request $request, string $field): ?array
    {
        if ($request->filled($field.'_markdown')) {
            $markdown = (string) $request->input($field.'_markdown');

            return [
                $field             => app(MarkdownToEditorJs::class)->convert($markdown),
                $field.'_editor'   => 'markdown',
                $field.'_markdown' => $markdown,
            ];
        }

        foreach ([$field.'_html', $field] as $key) {
            if ($request->has($key)) {
                return [
                    $field             => EditorContent::fromInput($request->input($key)),
                    $field.'_editor'   => 'blocks',
                    $field.'_markdown' => null,
                ];
            }
        }

        return null;
    }

    /** @return list<int> */
    protected function idsByName(string $model, array $names, string $field): array
    {
        $ids = [];
        $unknown = [];
        foreach (array_unique(array_filter(array_map('trim', $names))) as $name) {
            $record = $model::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            $record ? $ids[] = $record->id : $unknown[] = $name;
        }

        if ($unknown) {
            $type = $model === Theme::class ? 'Thème(s)' : 'Compétence(s)';
            throw ValidationException::withMessages([$field => "{$type} inconnu(s) : ".implode(', ', $unknown).'. Créez-les d\'abord depuis l\'administration.']);
        }

        return $ids;
    }

    /** Auteur principal : tout compte actif (personne, bot, compte de service), par e-mail ou identifiant. */
    protected function authorAccount(string $emailOrUsername): User
    {
        return User::articleAuthors()->where(fn ($q) => $q->where('email', $emailOrUsername)->orWhere('username', $emailOrUsername))->first()
            ?? throw ValidationException::withMessages(['author' => "Aucun compte actif ne correspond à « {$emailOrUsername} »."]);
    }

    protected function humanByEmail(string $email, string $field): User
    {
        return User::humans()->where('email', $email)->first()
            ?? throw ValidationException::withMessages([$field => "Aucun compte ne correspond à l'adresse {$email}."]);
    }
}
