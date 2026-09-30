<?php

namespace App\Services\Markdown;

use App\Services\Transfer\HtmlToEditorJs;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Convertit du Markdown (GitHub Flavored Markdown) en contenu Editor.js.
 *
 * Markdown → HTML (CommonMark) → blocs (HtmlToEditorJs). Le HTML écrit dans
 * le Markdown (couleurs, alignement…) est conservé ; il est de toute façon
 * nettoyé à l'affichage par EditorJsRenderer.
 */
class MarkdownToEditorJs
{
    protected ?MarkdownConverter $converter = null;

    public function __construct(protected HtmlToEditorJs $htmlConverter)
    {
    }

    /** @return array{time: int, blocks: list<array<string, mixed>>, version: string} */
    public function convert(string $markdown): array
    {
        $html = $this->converter()->convert($markdown)->getContent();

        return $this->htmlConverter->withImageResolver(null)->convert($this->normalize($html));
    }

    /**
     * Harmonise le HTML produit avec celui des outils Editor.js,
     * pour un rendu identique quel que soit l'éditeur utilisé.
     */
    protected function normalize(string $html): string
    {
        $html = preg_replace('#<pre><code(\s[^>]*)?>#i', '<pre><x-code$1>', $html);
        $html = preg_replace('#</code></pre>#i', '</x-code></pre>', $html);
        $html = preg_replace('#<code>#i', '<code class="inline-code">', $html);
        $html = str_replace(['<x-code', '</x-code>'], ['<code', '</code>'], $html);

        return preg_replace(
            ['#<(/?)strong>#i', '#<(/?)em>#i', '#<(/?)del>#i'],
            ['<$1b>', '<$1i>', '<$1s>'],
            $html
        );
    }

    protected function converter(): MarkdownConverter
    {
        if ($this->converter) {
            return $this->converter;
        }

        $environment = new Environment([
            'html_input'         => 'allow',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => 20,
            // Les <iframe> (vidéos) sont autorisées ici : seules YouTube, Vimeo et CodePen
            // sont conservées par la conversion, puis validées au rendu (EditorJsRenderer).
            'disallowed_raw_html' => [
                'disallowed_tags' => ['title', 'textarea', 'style', 'xmp', 'noembed', 'noframes', 'script', 'plaintext'],
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);

        return $this->converter = new MarkdownConverter($environment);
    }
}
