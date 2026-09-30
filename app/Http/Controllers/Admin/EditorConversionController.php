<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EditorJsRenderer;
use App\Services\Markdown\EditorJsToMarkdown;
use App\Services\Markdown\MarkdownToEditorJs;
use App\Support\EditorContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Conversions utilisées par l'éditeur lors du passage visuel ⇄ Markdown,
 * et aperçu du Markdown (rendu identique à celui du site public).
 */
class EditorConversionController extends Controller
{
    public function toMarkdown(Request $request, EditorJsToMarkdown $converter): JsonResponse
    {
        $request->validate(['content' => ['nullable', 'array']]);

        return response()->json([
            'markdown' => $converter->convert(EditorContent::fromInput($request->input('content'))),
        ]);
    }

    public function toBlocks(Request $request, MarkdownToEditorJs $converter, EditorJsRenderer $renderer): JsonResponse
    {
        $request->validate(['markdown' => ['nullable', 'string', 'max:1000000']]);

        $content = $converter->convert((string) $request->input('markdown', ''));

        return response()->json([
            'content' => $content,
            'html'    => $renderer->render($content),
        ]);
    }
}
