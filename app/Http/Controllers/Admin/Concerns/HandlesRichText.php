<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\Markdown\MarkdownToEditorJs;
use App\Support\EditorContent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Champ de texte enrichi éditable avec l'éditeur visuel (Editor.js) ou en Markdown.
 *
 * Champs du formulaire : {champ} (JSON Editor.js), {champ}_editor (blocks|markdown)
 * et {champ}_markdown. Le contenu enregistré est toujours au format Editor.js,
 * pour un rendu identique quel que soit l'éditeur ; la source Markdown est
 * conservée pour être retrouvée telle quelle.
 *
 * @return array<string, mixed> [champ => blocs, champ_editor => …, champ_markdown => …]
 */
trait HandlesRichText
{
    protected function richText(Request $request, string $field, bool $required, string $label): array
    {
        $request->validate([
            $field.'_editor'   => ['nullable', Rule::in(['blocks', 'markdown'])],
            $field.'_markdown' => ['nullable', 'string', 'max:1000000'],
            $field             => ['nullable', 'string', 'max:2000000'],
        ]);

        $editor = $request->input($field.'_editor', 'blocks');

        if ($editor === 'markdown') {
            $markdown = (string) $request->input($field.'_markdown', '');
            $content = trim($markdown) === '' ? null : app(MarkdownToEditorJs::class)->convert($markdown);
        } else {
            $markdown = null;
            // Contenu Editor.js ; le texte brut reste accepté (converti en paragraphes).
            $content = EditorContent::fromInput($request->input($field));
        }

        if ($required && EditorContent::isEmpty($content)) {
            throw ValidationException::withMessages([$field => "Le champ {$label} est obligatoire."]);
        }

        return [
            $field             => $content,
            $field.'_editor'   => $editor,
            $field.'_markdown' => $markdown,
        ];
    }
}
