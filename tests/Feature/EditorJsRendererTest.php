<?php

namespace Tests\Feature;

use App\Services\EditorJsRenderer;
use Tests\TestCase;

class EditorJsRendererTest extends TestCase
{
    protected function render(array $blocks): string
    {
        return app(EditorJsRenderer::class)->render(['blocks' => $blocks]);
    }

    public function test_renders_basic_blocks_with_alignment(): void
    {
        $html = $this->render([
            ['type' => 'header', 'data' => ['text' => 'Titre', 'level' => 3], 'tunes' => ['alignment' => ['alignment' => 'center']]],
            ['type' => 'paragraph', 'data' => ['text' => '<b>Gras</b> et <mark class="cdx-marker">surligné</mark>']],
            ['type' => 'code', 'data' => ['code' => '<?php echo 1;']],
            ['type' => 'delimiter', 'data' => []],
        ]);

        $this->assertStringContainsString('<h3 class="text-center">Titre</h3>', $html);
        $this->assertStringContainsString('<b>Gras</b>', $html);
        $this->assertStringContainsString('<mark class="cdx-marker">surligné</mark>', $html);
        $this->assertStringContainsString('&lt;?php echo 1;', $html);
        $this->assertStringContainsString('editor-delimiter', $html);
    }

    public function test_strips_dangerous_markup(): void
    {
        $html = $this->render([
            ['type' => 'paragraph', 'data' => ['text' => '<img src=x onerror=alert(1)><a href="javascript:alert(1)">lien</a><span style="position:fixed;color:red">x</span>']],
            ['type' => 'image', 'data' => ['file' => ['url' => 'javascript:alert(1)'], 'caption' => '']],
            ['type' => 'embed', 'data' => ['service' => 'youtube', 'embed' => 'https://evil.example/embed/x']],
            ['type' => 'raw', 'data' => ['html' => '<script>alert(1)</script>']],
        ]);

        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('position', $html);
        $this->assertStringContainsString('color:#FF0000', str_replace(' ', '', $html));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_renders_nested_and_legacy_lists(): void
    {
        $html = $this->render([
            ['type' => 'list', 'data' => ['style' => 'ordered', 'items' => [
                ['content' => 'Un', 'meta' => [], 'items' => [['content' => 'Un.1', 'meta' => [], 'items' => []]]],
            ]]],
            ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['Ancien format']]],
            ['type' => 'list', 'data' => ['style' => 'checklist', 'items' => [['content' => 'Fait', 'meta' => ['checked' => true], 'items' => []]]]],
        ]);

        $this->assertStringContainsString('<ol><li>Un<ol><li>Un.1</li></ol></li></ol>', $html);
        $this->assertStringContainsString('<ul><li>Ancien format</li></ul>', $html);
        $this->assertStringContainsString('editor-check is-checked', $html);
    }

    public function test_allows_whitelisted_embeds_and_local_images(): void
    {
        $html = $this->render([
            ['type' => 'embed', 'data' => ['service' => 'youtube', 'embed' => 'https://www.youtube.com/embed/abc', 'caption' => 'Vidéo']],
            ['type' => 'image', 'data' => ['file' => ['url' => '/storage/editor/a.png'], 'caption' => 'Schéma', 'stretched' => true]],
        ]);

        $this->assertStringContainsString('src="https://www.youtube.com/embed/abc"', $html);
        $this->assertStringContainsString('src="/storage/editor/a.png"', $html);
        $this->assertStringContainsString('is-stretched', $html);
    }

    public function test_invalid_input_renders_nothing(): void
    {
        $renderer = app(EditorJsRenderer::class);

        $this->assertSame('', $renderer->render(null));
        $this->assertSame('', $renderer->render('pas du json'));
        $this->assertSame('', $renderer->render(['blocks' => 'x']));
    }
}
