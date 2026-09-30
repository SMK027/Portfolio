<?php

namespace Tests\Feature;

use App\Services\EditorJsRenderer;
use App\Services\Markdown\EditorJsToMarkdown;
use App\Services\Markdown\MarkdownToEditorJs;
use Tests\TestCase;

class MarkdownConversionTest extends TestCase
{
    /** Contenu couvrant tous les blocs et styles proposés par l'éditeur visuel. */
    public static function richContent(): array
    {
        $item = fn ($content, $items = [], $meta = []) => ['content' => $content, 'meta' => $meta, 'items' => $items];

        return ['blocks' => [
            ['type' => 'header', 'data' => ['text' => 'Titre <i>principal</i>', 'level' => 2]],
            ['type' => 'header', 'data' => ['text' => 'Titre centré', 'level' => 3], 'tunes' => ['alignment' => ['alignment' => 'center']]],
            ['type' => 'paragraph', 'data' => ['text' => 'Du <b>gras</b>, de l\'<i>italique</i>, un <a href="https://example.com/a_b">lien</a>, du <code class="inline-code">code</code>, '
                .'<u>souligné</u>, <mark class="cdx-marker">surligné</mark>, <font color="#dc2626">rouge</font> et <s>barré</s>.<br>Nouvelle ligne avec * étoile _ et 3 &lt; 5.']],
            ['type' => 'paragraph', 'data' => ['text' => 'Paragraphe justifié'], 'tunes' => ['alignment' => ['alignment' => 'justify']]],
            ['type' => 'paragraph', 'data' => ['text' => '# pas un titre']],
            ['type' => 'list', 'data' => ['style' => 'unordered', 'meta' => [], 'items' => [$item('Un'), $item('Deux', [$item('Deux.1'), $item('Deux.2', [$item('Profond')])])]]],
            ['type' => 'list', 'data' => ['style' => 'ordered', 'meta' => ['counterType' => 'numeric', 'start' => 1], 'items' => [$item('Premier'), $item('<b>Second</b>', [$item('Sous')])]]],
            ['type' => 'list', 'data' => ['style' => 'checklist', 'meta' => [], 'items' => [$item('Fait', [], ['checked' => true]), $item('À faire', [], ['checked' => false])]]],
            ['type' => 'quote', 'data' => ['text' => 'La <b>simplicité</b>', 'caption' => 'Léonard', 'alignment' => 'left']],
            ['type' => 'quote', 'data' => ['text' => 'Citation centrée', 'caption' => '', 'alignment' => 'center']],
            ['type' => 'code', 'data' => ['code' => "<?php\necho '```';"]],
            ['type' => 'delimiter', 'data' => []],
            ['type' => 'image', 'data' => ['file' => ['url' => '/storage/editor/a.png'], 'caption' => 'Schéma réseau', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]],
            ['type' => 'image', 'data' => ['file' => ['url' => '/storage/editor/b.png'], 'caption' => 'Avec <b>options</b>', 'withBorder' => true, 'withBackground' => false, 'stretched' => true]],
            ['type' => 'table', 'data' => ['withHeadings' => true, 'content' => [['Nom', 'Valeur'], ['A | B', '<b>1</b>']]]],
            ['type' => 'table', 'data' => ['withHeadings' => false, 'content' => [['x', 'y']]]],
            ['type' => 'warning', 'data' => ['title' => 'Attention', 'message' => 'Message <i>important</i>']],
            ['type' => 'embed', 'data' => ['service' => 'youtube', 'source' => 'https://www.youtube.com/watch?v=abc123', 'embed' => 'https://www.youtube.com/embed/abc123', 'width' => 580, 'height' => 320, 'caption' => 'Vidéo']],
            ['type' => 'embed', 'data' => ['service' => 'vimeo', 'source' => 'https://vimeo.com/42', 'embed' => 'https://player.vimeo.com/video/42?title=0&byline=0', 'width' => 580, 'height' => 320, 'caption' => '']],
        ]];
    }

    protected function roundTrip(array $content): array
    {
        $markdown = app(EditorJsToMarkdown::class)->convert($content);

        return app(MarkdownToEditorJs::class)->convert($markdown);
    }

    public function test_every_block_type_survives_a_round_trip(): void
    {
        $original = self::richContent();
        $result = $this->roundTrip($original);

        $this->assertSame(array_column($original['blocks'], 'type'), array_column($result['blocks'], 'type'));

        foreach ($original['blocks'] as $i => $block) {
            $this->assertSame($block['data'] + ['_tunes' => $block['tunes'] ?? null], $result['blocks'][$i]['data'] + ['_tunes' => $result['blocks'][$i]['tunes'] ?? null], "Bloc {$i} ({$block['type']})");
        }
    }

    public function test_rendering_is_identical_whatever_the_editor(): void
    {
        $renderer = app(EditorJsRenderer::class);
        $original = self::richContent();

        $this->assertSame($renderer->render($original), $renderer->render($this->roundTrip($original)));
    }

    public function test_markdown_is_stable_after_a_round_trip(): void
    {
        $markdown = app(EditorJsToMarkdown::class)->convert(self::richContent());

        $this->assertSame($markdown, app(EditorJsToMarkdown::class)->convert(app(MarkdownToEditorJs::class)->convert($markdown)));
    }

    public function test_hand_written_markdown(): void
    {
        $content = app(MarkdownToEditorJs::class)->convert(<<<'MD'
        # Titre
        Texte **gras**, *italique*, ~~barré~~ et `code`.

        * a
        * b
            1. b1

        - [x] fait

        | A | B |
        |---|---|
        | 1 | 2 |

        ```js
        console.log(1);
        ```

        ![Légende](https://example.com/i.png)
        MD);

        $this->assertSame(['header', 'paragraph', 'list', 'list', 'table', 'code', 'image'], array_column($content['blocks'], 'type'));
        $this->assertSame('Texte <b>gras</b>, <i>italique</i>, <s>barré</s> et <code class="inline-code">code</code>.', $content['blocks'][1]['data']['text']);
        $this->assertSame('b1', $content['blocks'][2]['data']['items'][1]['items'][0]['content']);
        $this->assertSame('checklist', $content['blocks'][3]['data']['style']);
        $this->assertTrue($content['blocks'][3]['data']['items'][0]['meta']['checked']);
        $this->assertSame('console.log(1);', $content['blocks'][5]['data']['code']);
        $this->assertSame('Légende', $content['blocks'][6]['data']['caption']);
    }

    public function test_dangerous_markdown_is_neutralised_at_render(): void
    {
        $content = app(MarkdownToEditorJs::class)->convert("[clic](javascript:alert(1))\n\n<script>alert(2)</script>\n\n<img src=x onerror=alert(3)>");
        $html = app(EditorJsRenderer::class)->render($content);

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }
}
