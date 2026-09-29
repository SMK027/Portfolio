<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Convertit le JSON produit par Editor.js en HTML sûr.
 *
 * Chaque bloc est rendu explicitement (aucun HTML brut n'est accepté),
 * et le texte enrichi des blocs passe par HTMLPurifier avec une liste
 * blanche de balises de mise en forme.
 */
class EditorJsRenderer
{
    protected ?HTMLPurifier $purifier = null;

    /** Services autorisés pour le bloc "embed" et préfixe d'URL attendu. */
    protected const EMBED_PREFIXES = [
        'youtube'      => 'https://www.youtube.com/embed/',
        'vimeo'        => 'https://player.vimeo.com/video/',
        'codepen'      => 'https://codepen.io/',
        'twitch-video' => 'https://player.twitch.tv/',
    ];

    protected const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    /**
     * @param  array<string, mixed>|string|null  $data
     */
    public function render(array|string|null $data): string
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data) || ! is_array($data['blocks'] ?? null)) {
            return '';
        }

        return collect($data['blocks'])
            ->filter(fn ($block) => is_array($block) && isset($block['type']))
            ->map(fn (array $block) => $this->renderBlock($block))
            ->filter()
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function renderBlock(array $block): string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $align = $this->alignmentClass($block);

        return match ($block['type']) {
            'paragraph' => $this->wrap('p', $this->inline($data['text'] ?? ''), $align),
            'header'    => $this->header($data, $align),
            'list'      => $this->list($data),
            'checklist' => $this->list(['style' => 'checklist', 'items' => array_map(
                fn ($item) => ['content' => $item['text'] ?? '', 'meta' => ['checked' => (bool) ($item['checked'] ?? false)]],
                (array) ($data['items'] ?? [])
            )]),
            'quote'     => $this->quote($data, $align),
            'code'      => '<pre class="editor-code"><code>'.e((string) ($data['code'] ?? '')).'</code></pre>',
            'delimiter' => '<hr class="editor-delimiter">',
            'image'     => $this->image($data),
            'table'     => $this->table($data),
            'warning'   => $this->warning($data),
            'embed'     => $this->embed($data),
            default     => '',
        };
    }

    protected function wrap(string $tag, string $content, string $class = ''): string
    {
        if (trim(strip_tags($content, '<img>')) === '' && $tag === 'p') {
            return '';
        }

        $classAttr = $class ? ' class="'.$class.'"' : '';

        return "<{$tag}{$classAttr}>{$content}</{$tag}>";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function header(array $data, string $align): string
    {
        $level = max(2, min(6, (int) ($data['level'] ?? 2)));

        return $this->wrap("h{$level}", $this->inline($data['text'] ?? ''), $align);
    }

    /**
     * Gère le format @editorjs/list v2 (items objets imbriqués)
     * ainsi que l'ancien format (items chaînes).
     *
     * @param  array<string, mixed>  $data
     */
    protected function list(array $data): string
    {
        $style = $data['style'] ?? 'unordered';

        return $this->listItems((array) ($data['items'] ?? []), $style);
    }

    /**
     * @param  array<int, mixed>  $items
     */
    protected function listItems(array $items, string $style, int $depth = 0): string
    {
        if ($items === [] || $depth > 10) {
            return '';
        }

        $tag = $style === 'ordered' ? 'ol' : 'ul';
        $class = $style === 'checklist' ? ' class="editor-checklist"' : '';
        $html = "<{$tag}{$class}>";

        foreach ($items as $item) {
            $content = is_array($item) ? ($item['content'] ?? $item['text'] ?? '') : $item;
            $children = is_array($item) ? (array) ($item['items'] ?? []) : [];
            $text = $this->inline((string) $content);

            if ($style === 'checklist') {
                $checked = is_array($item) && (($item['meta']['checked'] ?? false) === true);
                $text = '<span class="editor-check'.($checked ? ' is-checked' : '').'" aria-hidden="true"></span>'
                    .'<span class="sr-only">'.($checked ? 'Fait : ' : 'À faire : ').'</span>'.$text;
            }

            $html .= '<li>'.$text.$this->listItems($children, $style, $depth + 1).'</li>';
        }

        return $html."</{$tag}>";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function quote(array $data, string $align): string
    {
        $caption = trim(strip_tags((string) ($data['caption'] ?? ''))) !== ''
            ? '<figcaption>'.$this->inline($data['caption']).'</figcaption>'
            : '';

        if (! $align && ($data['alignment'] ?? null) === 'center') {
            $align = 'text-center';
        }

        return '<figure class="editor-quote'.($align ? ' '.$align : '').'"><blockquote>'
            .$this->inline($data['text'] ?? '').'</blockquote>'.$caption.'</figure>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function image(array $data): string
    {
        $url = $this->safeUrl($data['file']['url'] ?? $data['url'] ?? null);

        if (! $url) {
            return '';
        }

        $caption = (string) ($data['caption'] ?? '');
        $classes = ['editor-image'];
        foreach (['withBorder' => 'with-border', 'withBackground' => 'with-background', 'stretched' => 'is-stretched'] as $key => $class) {
            if (! empty($data[$key])) {
                $classes[] = $class;
            }
        }

        $alt = e(trim(html_entity_decode(strip_tags($caption))));
        $figcaption = trim(strip_tags($caption)) !== '' ? '<figcaption>'.$this->inline($caption).'</figcaption>' : '';

        return '<figure class="'.implode(' ', $classes).'"><img src="'.e($url).'" alt="'.$alt.'" loading="lazy">'
            .$figcaption.'</figure>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function table(array $data): string
    {
        $rows = array_values(array_filter((array) ($data['content'] ?? []), 'is_array'));

        if ($rows === []) {
            return '';
        }

        $withHeadings = ! empty($data['withHeadings']);
        $html = '<div class="editor-table"><table>';

        foreach ($rows as $i => $row) {
            $cell = $withHeadings && $i === 0 ? 'th' : 'td';
            if ($withHeadings && $i === 0) {
                $html .= '<thead>';
            } elseif ($i === ($withHeadings ? 1 : 0)) {
                $html .= '<tbody>';
            }

            $html .= '<tr>'.collect($row)->map(fn ($c) => "<{$cell}>".$this->inline((string) $c)."</{$cell}>")->implode('').'</tr>';

            if ($withHeadings && $i === 0) {
                $html .= '</thead>';
            }
        }

        if (count($rows) > ($withHeadings ? 1 : 0)) {
            $html .= '</tbody>';
        }

        return $html.'</table></div>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function warning(array $data): string
    {
        return '<aside class="editor-warning" role="note"><p class="editor-warning-title">'
            .$this->inline($data['title'] ?? '').'</p><p>'.$this->inline($data['message'] ?? '').'</p></aside>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function embed(array $data): string
    {
        $service = (string) ($data['service'] ?? '');
        $src = (string) ($data['embed'] ?? '');
        $prefix = self::EMBED_PREFIXES[$service] ?? null;

        if (! $prefix || ! str_starts_with($src, $prefix)) {
            return '';
        }

        $caption = trim(strip_tags((string) ($data['caption'] ?? ''))) !== ''
            ? '<figcaption>'.$this->inline($data['caption']).'</figcaption>'
            : '';

        return '<figure class="editor-embed"><div class="editor-embed-frame"><iframe src="'.e($src).'" '
            .'loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" '
            .'sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"></iframe></div>'.$caption.'</figure>';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function alignmentClass(array $block): string
    {
        $alignment = $block['tunes']['alignment']['alignment'] ?? null;

        return in_array($alignment, self::ALIGNMENTS, true) && $alignment !== 'left' ? 'text-'.$alignment : '';
    }

    /**
     * Autorise uniquement les URL http(s) et les chemins locaux absolus.
     */
    protected function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /**
     * Nettoie le HTML en ligne (gras, italique, liens, couleurs…).
     */
    public function inline(mixed $html): string
    {
        return $this->purifier()->purify((string) $html);
    }

    protected function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $cache = storage_path('framework/cache/htmlpurifier');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cache);
        $config->set('HTML.Allowed', 'b,strong,i,em,u,s,sub,sup,br,code[class],a[href|title|target|rel],mark[class|style],span[class|style],font[color|style]');
        $config->set('CSS.AllowedProperties', ['color', 'background-color']);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.AllowedClasses', ['inline-code', 'cdx-marker']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('AutoFormat.RemoveEmpty', false);

        // <mark> (HTML5, outil "Marker") n'est pas connu nativement de HTMLPurifier.
        $config->set('HTML.DefinitionID', 'portfolio-editorjs');
        $config->set('HTML.DefinitionRev', 1);
        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            $definition->addElement('mark', 'Inline', 'Inline', 'Common');
        }

        return $this->purifier = new HTMLPurifier($config);
    }
}
