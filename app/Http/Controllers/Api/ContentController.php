<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\Transfer\ContentTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Export / import JSON (même format que la page « Import / export »).
 */
class ContentController extends Controller
{
    public function export(Request $request, ContentTransfer $transfer, AuditTrail $audit): JsonResponse
    {
        $data = $request->validate([
            'sections'   => ['nullable', 'array'],
            'sections.*' => [Rule::in(array_keys(ContentTransfer::SECTIONS))],
        ]);
        $sections = $data['sections'] ?? array_keys(ContentTransfer::SECTIONS);

        $audit->record('content.exported', meta: ['sections' => $sections]);

        return response()->json($transfer->export($sections));
    }

    /**
     * « dry_run » est obligatoire : true pour simuler, false pour enregistrer.
     */
    public function import(Request $request, ContentTransfer $transfer, AuditTrail $audit): JsonResponse
    {
        $data = $request->validate([
            'data'            => ['required', 'array'],
            'sections'        => ['nullable', 'array'],
            'sections.*'      => [Rule::in(array_keys(ContentTransfer::SECTIONS))],
            'dry_run'         => ['required', 'boolean'],
            'download_images' => ['nullable', 'boolean'],
        ]);

        if ($error = $transfer->envelopeError($data['data'])) {
            return response()->json(['message' => $error], 422);
        }

        $dryRun = (bool) $data['dry_run'];
        $report = $transfer->import(
            $data['data'],
            $data['sections'] ?? array_keys(ContentTransfer::SECTIONS),
            $dryRun,
            $request->user(),
            (bool) ($data['download_images'] ?? false),
        );

        if (! $dryRun) {
            $audit->record('content.imported', meta: ['résultat' => collect($report)->reject(fn ($l, $k) => str_starts_with($k, '_'))->all()]);
        }

        return response()->json(['dry_run' => $dryRun, 'report' => $report]);
    }
}
