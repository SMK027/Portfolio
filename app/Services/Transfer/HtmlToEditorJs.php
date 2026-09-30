<?php

namespace App\Services\Transfer;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Convertit du HTML (ancien site, export d'un autre outil) en blocs Editor.js.
 * Le HTML en ligne des paragraphes est conservé tel quel : il est nettoyé à
 * l'affichage par EditorJsRenderer (HTMLPurifier).
 */
class HtmlToEditorJs
{
    /** Balises de mise en forme conservées dans les paragraphes. */
    protected const INLINE_TAGS = ['a', 'b', 'strong', 'i', 'em', 'u', 's', 'mark', 'code', 'span', 'font', 'sub', 'sup', 'br', 'small'];

    /** Conteneurs dont on parcourt simplement le contenu. */
    protected const CONTAINERS = ['div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'body', 'center'];

    /** Préfixes d'URL d'intégration reconnus (identiques à EditorJsRenderer). */
    protected const EMBEDS = [
        'youtube' => '#^https?://(?:www\.)?(?:youtube(?:-nocookie)?\.com/embed/|youtu\.be/)([\w-]+)#i',
        'vimeo'   => '#^https?://player\.vimeo\.com/video/(\d+)#i',
    ];

    /** @var list<array<string, mixed>> */
    protected array $blocks = [];

    /** @var callable|null fn(string $src): ?string — réécriture des images (import sur le site) */
    protected $imageResolver = null;

    public function withImageResolver(?callable $resolver): static
    {
        $this->imageResolver = $resolver;

        return $this;
    }

    /** @return array{time: int, blocks: list<array<string, mixed>>, version: string} */
    public function convert(string $html): array
    {
        $this->blocks = [];

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $this->walk($body);
        }

        return ['time' => (int) (microtime(true) * 1000), 'blocks' => $this->blocks, 'version' => '2.31.0'];
    }

    protected function walk(DOMNode $parent): void
    {
        $inline = '';

        foreach ($parent->childNodes as $node) {
            if ($this->isInline($node)) {
                $inline .= $this->outerHtml($node);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue; // commentaires, instructions…
            }

            $this->flushParagraph($inline);
            $inline = '';
            $this->block($node);
        }

        $this->flushParagraph($inline);
    }

    protected function block(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);

        match (true) {
            in_array($tag, self::CONTAINERS, true)         => $this->walk($el),
            (bool) preg_match('/^h[1-6]$/', $tag)          => $this->add('header', [
                'text'  => $this->innerHtml($el),
                'level' => max(2, min(4, (int) substr($tag, 1) + ($tag === 'h1' ? 1 : 0))),
            ]),
            $tag === 'p'                                   => $this->paragraphWithImages($el),
            $tag === 'ul', $tag === 'ol'                   => $this->add('list', [
                'style' => $tag === 'ol' ? 'ordered' : 'unordered',
                'meta'  => [],
                'items' => $this->listItems($el),
            ]),
            $tag === 'blockquote'                          => $this->add('quote', [
                'text'      => trim(strip_tags($this->innerHtml($el), '<b><strong><i><em><a><br>')),
                'caption'   => '',
                'alignment' => 'left',
            ]),
            $tag === 'pre'                                 => $this->add('code', ['code' => rtrim($el->textContent)]),
            $tag === 'hr'                                  => $this->add('delimiter', []),
            $tag === 'img'                                 => $this->image($el, ''),
            $tag === 'figure'                              => $this->figure($el),
            $tag === 'table'                               => $this->table($el),
            $tag === 'iframe'                              => $this->iframe($el),
            default                                        => $this->flushParagraph($this->innerHtml($el)),
        };
    }

    /** Un <p> peut contenir des images : elles deviennent des blocs à part. */
    protected function paragraphWithImages(DOMElement $p): void
    {
        if ($p->getElementsByTagName('img')->length === 0 && $p->getElementsByTagName('iframe')->length === 0) {
            $this->flushParagraph($this->innerHtml($p));

            return;
        }

        $this->walk($p);
    }

    /** @return list<array<string, mixed>> */
    protected function listItems(DOMElement $list): array
    {
        $items = [];
        foreach ($list->childNodes as $li) {
            if (! $li instanceof DOMElement || strtolower($li->tagName) !== 'li') {
                continue;
            }

            $content = '';
            $children = [];
            foreach ($li->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                    $children = array_merge($children, $this->listItems($child));
                } else {
                    $content .= $this->outerHtml($child);
                }
            }

            $items[] = ['content' => trim($content), 'meta' => [], 'items' => $children];
        }

        return $items;
    }

    protected function image(DOMElement $img, string $caption): void
    {
        $src = trim($img->getAttribute('src'));
        if ($src === '') {
            return;
        }

        if ($this->imageResolver) {
            $src = ($this->imageResolver)($src) ?? $src;
        }

        $this->add('image', [
            'file'           => ['url' => $src],
            'caption'        => $caption !== '' ? $caption : trim($img->getAttribute('alt')),
            'withBorder'     => false,
            'withBackground' => false,
            'stretched'      => false,
        ]);
    }

    protected function figure(DOMElement $figure): void
    {
        $caption = '';
        foreach ($figure->getElementsByTagName('figcaption') as $fc) {
            $caption = trim($this->innerHtml($fc));
        }

        $img = $figure->getElementsByTagName('img')->item(0);
        if ($img instanceof DOMElement) {
            $this->image($img, $caption);

            return;
        }

        $iframe = $figure->getElementsByTagName('iframe')->item(0);
        if ($iframe instanceof DOMElement) {
            $this->iframe($iframe, $caption);

            return;
        }

        $this->walk($figure);
    }

    protected function table(DOMElement $table): void
    {
        $rows = [];
        $withHeadings = false;
        foreach ($table->getElementsByTagName('tr') as $i => $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    $cells[] = trim($this->innerHtml($cell));
                    $withHeadings = $withHeadings || ($i === 0 && strtolower($cell->tagName) === 'th');
                }
            }
            if ($cells) {
                $rows[] = $cells;
            }
        }

        if ($rows) {
            $this->add('table', ['withHeadings' => $withHeadings, 'content' => $rows]);
        }
    }

    protected function iframe(DOMElement $iframe, string $caption = ''): void
    {
        $src = trim($iframe->getAttribute('src'));

        if (preg_match(self::EMBEDS['youtube'], $src, $m)) {
            $this->add('embed', [
                'service' => 'youtube', 'source' => 'https://www.youtube.com/watch?v='.$m[1],
                'embed' => 'https://www.youtube.com/embed/'.$m[1], 'width' => 580, 'height' => 320, 'caption' => $caption,
            ]);
        } elseif (preg_match(self::EMBEDS['vimeo'], $src, $m)) {
            $this->add('embed', [
                'service' => 'vimeo', 'source' => 'https://vimeo.com/'.$m[1],
                'embed' => 'https://player.vimeo.com/video/'.$m[1].'?title=0&byline=0', 'width' => 580, 'height' => 320, 'caption' => $caption,
            ]);
        } elseif (preg_match('#^https?://#i', $src)) {
            // Contenu intégré non pris en charge : conservé sous forme de lien.
            $label = htmlspecialchars($iframe->getAttribute('title') ?: $src, ENT_QUOTES);
            $this->flushParagraph('<a href="'.htmlspecialchars($src, ENT_QUOTES).'">'.$label.'</a>');
        }
    }

    protected function flushParagraph(string $html): void
    {
        $html = trim(preg_replace('/\s+/u', ' ', $html));
        $html = preg_replace('#^(<br\s*/?>\s*)+|(\s*<br\s*/?>)+$#i', '', $html);

        if (trim(strip_tags($html)) === '') {
            return;
        }

        $this->add('paragraph', ['text' => $html]);
    }

    /** @param array<string, mixed> $data */
    protected function add(string $type, array $data): void
    {
        $this->blocks[] = ['id' => substr(bin2hex(random_bytes(6)), 0, 10), 'type' => $type, 'data' => $data];
    }

    protected function isInline(DOMNode $node): bool
    {
        if ($node instanceof DOMText) {
            return true;
        }

        return $node instanceof DOMElement
            && in_array(strtolower($node->tagName), self::INLINE_TAGS, true)
            && $node->getElementsByTagName('img')->length === 0;
    }

    protected function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $this->outerHtml($child);
        }

        return $html;
    }

    protected function outerHtml(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->textContent, ENT_NOQUOTES);
        }

        return (string) $node->ownerDocument->saveHTML($node);
    }
}
