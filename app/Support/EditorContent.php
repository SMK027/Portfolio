<?php

namespace App\Support;

use App\Services\Transfer\HtmlToEditorJs;
use Illuminate\Support\Arr;

/**
 * Conversions autour du contenu Editor.js ({ time, blocks, version }).
 */
final class EditorContent
{
    /**
     * Normalise une saisie : contenu Editor.js (tableau ou JSON), HTML ou texte brut.
     *
     * @return array<string, mixed>|null
     */
    public static function fromInput(mixed $value, ?callable $imageResolver = null): ?array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return is_array($value['blocks'] ?? null)
                ? ['time' => $value['time'] ?? null, 'blocks' => array_values($value['blocks']), 'version' => $value['version'] ?? null]
                : null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        // HTML seulement en présence de vraies balises (« 3 < 5 » ou « <2> » restent du texte).
        return preg_match('#</?[a-z][a-z0-9-]*(\s[^<>]*)?/?>#i', $value)
            ? app(HtmlToEditorJs::class)->withImageResolver($imageResolver)->convert($value)
            : self::fromText($value);
    }

    /**
     * Texte brut → paragraphes (une ligne vide sépare deux paragraphes).
     *
     * @return array<string, mixed>
     */
    public static function fromText(string $text): array
    {
        $paragraphs = preg_split('/\R\s*\R/u', trim(str_replace("\r\n", "\n", $text))) ?: [];

        $blocks = [];
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) === '') {
                continue;
            }
            $blocks[] = [
                'id'   => substr(bin2hex(random_bytes(6)), 0, 10),
                'type' => 'paragraph',
                'data' => ['text' => nl2br(htmlspecialchars(trim($paragraph), ENT_QUOTES), false)],
            ];
        }

        return ['time' => (int) (microtime(true) * 1000), 'blocks' => $blocks, 'version' => '2.31.0'];
    }

    /** Texte brut du contenu (résumés, balises meta, temps de lecture). */
    public static function toText(?array $content): string
    {
        return collect($content['blocks'] ?? [])
            ->map(fn ($block) => collect(Arr::flatten((array) ($block['data'] ?? [])))
                ->filter(fn ($v) => is_string($v) && ! str_starts_with($v, 'http') && ! str_starts_with($v, '/'))
                ->map(fn ($v) => html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $v)), ENT_QUOTES | ENT_HTML5))
                ->implode(' '))
            ->map(fn ($text) => trim(preg_replace('/\s+/u', ' ', $text)))
            ->filter()
            ->implode("\n\n");
    }

    /** Le contenu contient-il au moins un bloc non vide ? */
    public static function isEmpty(?array $content): bool
    {
        return collect($content['blocks'] ?? [])->doesntContain(
            fn ($block) => ! in_array($block['type'] ?? '', ['paragraph', 'header'], true)
                || trim(strip_tags((string) ($block['data']['text'] ?? ''))) !== ''
        );
    }
}
