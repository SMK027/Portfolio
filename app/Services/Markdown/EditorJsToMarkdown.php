<?php

namespace App\Services\Markdown;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Convertit un contenu Editor.js en Markdown (GitHub Flavored Markdown).
 *
 * Ce que Markdown ne sait pas exprimer est conservé en HTML dans le texte,
 * pour que la conversion inverse (MarkdownToEditorJs) restitue le même style :
 * couleurs, surlignage, soulignement, alignement, options d'image,
 * avertissements, vidéos intégrées.
 */
class EditorJsToMarkdown
{
    public function convert(?array $content): string
    {
        $parts = [];
        foreach ($content['blocks'] ?? [] as $block) {
            $markdown = $this->block((array) $block);
            if ($markdown !== '') {
                $parts[] = $markdown;
            }
        }

        return implode("\n\n", $parts)."\n";
    }

    protected function block(array $block): string
    {
        $data = (array) ($block['data'] ?? []);
        $align = $block['tunes']['alignment']['alignment'] ?? 'left';
        $aligned = in_array($align, ['center', 'right', 'justify'], true);

        return match ($block['type'] ?? '') {
            'paragraph' => $aligned
                ? '<p align="'.$align.'">'.trim((string) ($data['text'] ?? '')).'</p>'
                : $this->inline((string) ($data['text'] ?? ''), true),
            'header'    => $this->header($data, $aligned ? $align : null),
            'list'      => $this->list((array) ($data['items'] ?? []), (string) ($data['style'] ?? 'unordered')),
            'checklist' => $this->list(array_map(
                fn ($i) => ['content' => $i['text'] ?? '', 'meta' => ['checked' => (bool) ($i['checked'] ?? false)], 'items' => []],
                (array) ($data['items'] ?? [])
            ), 'checklist'),
            'quote'     => $this->quote($data),
            'code'      => $this->code((string) ($data['code'] ?? '')),
            'delimiter' => '---',
            'image'     => $this->image($data),
            'table'     => $this->table($data),
            'warning'   => '<aside data-type="warning"><strong>'.trim((string) ($data['title'] ?? '')).'</strong><p>'.trim((string) ($data['message'] ?? '')).'</p></aside>',
            'embed'     => $this->embed($data),
            default     => '',
        };
    }

    protected function header(array $data, ?string $align): string
    {
        $level = max(1, min(6, (int) ($data['level'] ?? 2)));
        $text = (string) ($data['text'] ?? '');

        return $align
            ? "<h{$level} align=\"{$align}\">".trim($text)."</h{$level}>"
            : str_repeat('#', $level).' '.$this->inline($text);
    }

    protected function list(array $items, string $style, int $depth = 0): string
    {
        $lines = [];
        foreach (array_values($items) as $i => $item) {
            $item = is_array($item) ? $item : ['content' => (string) $item, 'items' => []];
            $marker = match ($style) {
                'ordered'   => ($i + 1).'. ',
                'checklist' => '- ['.(($item['meta']['checked'] ?? false) ? 'x' : ' ').'] ',
                default     => '- ',
            };
            $indent = str_repeat(' ', $depth);
            $lines[] = $indent.$marker.$this->inline((string) ($item['content'] ?? $item['text'] ?? ''));

            if (! empty($item['items'])) {
                // Les sous-éléments s'alignent sur le début du texte de l'élément parent.
                $lines[] = $this->list((array) $item['items'], $style, $depth + strlen($style === 'checklist' ? '- ' : $marker));
            }
        }

        return implode("\n", $lines);
    }

    protected function quote(array $data): string
    {
        $text = $this->inline((string) ($data['text'] ?? ''));
        $caption = trim((string) ($data['caption'] ?? ''));

        if (($data['alignment'] ?? 'left') === 'center') {
            return '<blockquote data-align="center"><p>'.trim((string) ($data['text'] ?? '')).'</p>'
                .($caption !== '' ? '<cite>'.$caption.'</cite>' : '').'</blockquote>';
        }

        $markdown = '> '.str_replace("\n", "\n> ", $text);

        return $caption !== '' ? $markdown."\n>\n> <cite>".$caption.'</cite>' : $markdown;
    }

    protected function code(string $code): string
    {
        // La clôture doit être plus longue que toute suite d'accents graves du code.
        preg_match_all('/`+/', $code, $m);
        $fence = str_repeat('`', max(3, ...array_map(fn ($s) => strlen($s) + 1, $m[0] ?: [''])));

        return $fence."\n".rtrim($code, "\n")."\n".$fence;
    }

    protected function image(array $data): string
    {
        $url = (string) ($data['file']['url'] ?? $data['url'] ?? '');
        if ($url === '') {
            return '';
        }

        $caption = trim((string) ($data['caption'] ?? ''));
        $flags = array_keys(array_filter([
            'border'     => ! empty($data['withBorder']),
            'background' => ! empty($data['withBackground']),
            'stretched'  => ! empty($data['stretched']),
        ]));

        // Options d'affichage ou légende mise en forme : figure HTML (conservée telle quelle).
        if ($flags || $caption !== strip_tags($caption)) {
            return '<figure'.($flags ? ' data-editor="'.implode(' ', $flags).'"' : '').'><img src="'.$this->attr($url).'" alt="">'
                .($caption !== '' ? '<figcaption>'.$caption.'</figcaption>' : '').'</figure>';
        }

        return '!['.$this->escapeText(html_entity_decode($caption, ENT_QUOTES | ENT_HTML5), true).']('.$this->url($url).')';
    }

    protected function table(array $data): string
    {
        $rows = array_values(array_filter((array) ($data['content'] ?? []), 'is_array'));
        if (! $rows) {
            return '';
        }

        // Markdown impose une ligne d'en-tête : sans en-têtes, on garde un tableau HTML.
        if (empty($data['withHeadings'])) {
            return '<table>'.implode('', array_map(
                fn ($row) => '<tr>'.implode('', array_map(fn ($c) => '<td>'.$c.'</td>', $row)).'</tr>',
                $rows
            )).'</table>';
        }

        $width = max(array_map('count', $rows));
        $line = fn (array $row) => '| '.implode(' | ', array_map(
            fn ($cell) => str_replace(["\n", '|'], [' ', '\\|'], $this->inline((string) $cell)),
            array_pad($row, $width, '')
        )).' |';

        return implode("\n", [
            $line($rows[0]),
            '|'.str_repeat(' --- |', $width),
            ...array_map($line, array_slice($rows, 1)),
        ]);
    }

    protected function embed(array $data): string
    {
        $src = (string) ($data['embed'] ?? '');
        if ($src === '') {
            return '';
        }

        $iframe = '<iframe src="'.$this->attr($src).'"></iframe>';
        $caption = trim((string) ($data['caption'] ?? ''));

        return $caption !== '' ? '<figure>'.$iframe.'<figcaption>'.$caption.'</figcaption></figure>' : $iframe;
    }

    /* ----------------------------------------------------------------------
     |  HTML en ligne → Markdown
     * -------------------------------------------------------------------- */

    protected function inline(string $html, bool $lineStart = false): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $markdown = trim($this->children($dom->getElementsByTagName('body')->item(0)));

        // Début de ligne ressemblant à une syntaxe Markdown (titre, liste, citation) : échappé.
        return preg_replace('/^(#{1,6}\s|[-+*]\s|\d+[.)]\s|>)/', '\\\\$1', $markdown);
    }

    protected function children(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->node($child);
        }

        return $out;
    }

    protected function node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $this->escapeText($node->textContent);
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        $inner = $this->children($node);

        // Balises sans attributs de style : syntaxe Markdown ; sinon, HTML conservé.
        $plain = ! $node->hasAttribute('style') && ! $node->hasAttribute('color');

        return match (true) {
            in_array($tag, ['b', 'strong'], true) && $plain && trim($inner) !== '' => $this->wrap('**', $inner),
            in_array($tag, ['i', 'em'], true) && $plain && trim($inner) !== ''     => $this->wrap('*', $inner),
            $tag === 'code' && $plain                                              => $this->inlineCode($node->textContent),
            $tag === 'a' && $node->getAttribute('href') !== '' && $plain           => '['.$inner.']('.$this->url($node->getAttribute('href')).')',
            $tag === 'br'                                                          => '<br>',
            default                                                                => $this->html($node, $inner),
        };
    }

    /** Élément conservé en HTML (souligné, surligné, couleur…), contenu converti. */
    protected function html(DOMElement $node, string $inner): string
    {
        $attributes = '';
        foreach ($node->attributes as $attribute) {
            $attributes .= ' '.$attribute->name.'="'.$this->attr($attribute->value).'"';
        }

        return '<'.strtolower($node->tagName).$attributes.'>'.$inner.'</'.strtolower($node->tagName).'>';
    }

    /** Les espaces aux bords d'une emphase la rendraient invalide : ils sont sortis. */
    protected function wrap(string $marker, string $inner): string
    {
        preg_match('/^(\s*)(.*?)(\s*)$/su', $inner, $m);

        return $m[1].$marker.$m[2].$marker.$m[3];
    }

    protected function inlineCode(string $code): string
    {
        preg_match_all('/`+/', $code, $m);
        $ticks = str_repeat('`', max(1, ...array_map(fn ($s) => strlen($s) + 1, $m[0] ?: [''])));
        $pad = str_starts_with($code, '`') || str_ends_with($code, '`') ? ' ' : '';

        return $ticks.$pad.$code.$pad.$ticks;
    }

    protected function escapeText(string $text, bool $inBrackets = false): string
    {
        $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false);

        return preg_replace('/([\\\\`*_\[\]|~])/', '\\\\$1', $text);
    }

    protected function url(string $url): string
    {
        return str_replace([' ', '(', ')'], ['%20', '%28', '%29'], $url);
    }

    protected function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
    }
}
