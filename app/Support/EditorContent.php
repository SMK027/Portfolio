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
            ->map(fn ($block) => collect(self::blockTexts((array) $block))
                ->map(fn ($v) => html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', ' ', (string) $v)), ENT_QUOTES | ENT_HTML5))
                ->implode(' '))
            ->map(fn ($text) => trim(preg_replace('/\s+/u', ' ', $text)))
            ->filter()
            ->implode("\n\n");
    }

    /**
     * Textes lisibles d'un bloc (les réglages techniques — style de liste, URL… — sont ignorés).
     *
     * @return list<string>
     */
    protected static function blockTexts(array $block): array
    {
        $data = (array) ($block['data'] ?? []);
        $listTexts = function (array $items) use (&$listTexts): array {
            $texts = [];
            foreach ($items as $item) {
                $texts[] = is_array($item) ? ($item['content'] ?? $item['text'] ?? '') : (string) $item;
                if (is_array($item) && ! empty($item['items'])) {
                    array_push($texts, ...$listTexts((array) $item['items']));
                }
            }

            return $texts;
        };

        return array_values(array_filter(match ($block['type'] ?? '') {
            'paragraph', 'header'  => [$data['text'] ?? ''],
            'list', 'checklist'    => $listTexts((array) ($data['items'] ?? [])),
            'quote'                => [$data['text'] ?? '', $data['caption'] ?? ''],
            'warning'              => [$data['title'] ?? '', $data['message'] ?? ''],
            'table'                => Arr::flatten((array) ($data['content'] ?? [])),
            'code'                 => [$data['code'] ?? ''],
            'image', 'embed'       => [$data['caption'] ?? ''],
            default                => [],
        }, fn ($v) => is_string($v) && trim($v) !== ''));
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
