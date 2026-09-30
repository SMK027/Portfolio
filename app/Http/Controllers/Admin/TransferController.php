<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\Transfer\ContentTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Import / export JSON du contenu.
 * L'import se fait en deux temps : simulation (rien n'est enregistré) puis confirmation.
 */
class TransferController extends Controller
{
    protected const PENDING = 'transfer.pending';

    protected const DIRECTORY = 'imports';

    public function index(Request $request, ContentTransfer $transfer): View
    {
        return view('admin.transfer.index', [
            'sections' => ContentTransfer::SECTIONS,
            'pending'  => $request->session()->get(self::PENDING),
            'report'   => $request->session()->get('transfer.report'),
            'mode'     => $request->session()->get('transfer.mode'),
        ]);
    }

    public function export(Request $request, ContentTransfer $transfer): Response
    {
        $data = $request->validate([
            'sections'   => ['required', 'array', 'min:1'],
            'sections.*' => [Rule::in(array_keys(ContentTransfer::SECTIONS))],
        ], ['sections.required' => 'Choisissez au moins une section à exporter.']);

        app(AuditTrail::class)->record('content.exported', meta: ['sections' => $data['sections']]);

        return $this->jsonDownload($transfer->export($data['sections']), 'portfolio-export-'.now()->format('Y-m-d-His').'.json');
    }

    public function example(ContentTransfer $transfer): Response
    {
        return $this->jsonDownload($transfer->example(), 'portfolio-modele.json');
    }

    /** Étape 1 : téléversement et simulation. */
    public function preview(Request $request, ContentTransfer $transfer): RedirectResponse
    {
        $data = $request->validate([
            'file'            => ['required', 'file', 'max:20480', 'extensions:json'],
            'sections'        => ['required', 'array', 'min:1'],
            'sections.*'      => [Rule::in(array_keys(ContentTransfer::SECTIONS))],
            'download_images' => ['nullable', 'boolean'],
        ], ['sections.required' => 'Choisissez au moins une section à importer.'], ['file' => 'fichier']);

        $payload = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);
        if ($error = $transfer->envelopeError($payload)) {
            return back()->withErrors(['file' => json_last_error() !== JSON_ERROR_NONE ? 'Le fichier n\'est pas un JSON valide ('.json_last_error_msg().').' : $error]);
        }

        $this->discardPending($request);

        $path = self::DIRECTORY.'/'.Str::uuid().'.json';
        Storage::disk('local')->put($path, json_encode($payload));

        $pending = [
            'path'            => $path,
            'name'            => $request->file('file')->getClientOriginalName(),
            'sections'        => $data['sections'],
            'download_images' => $request->boolean('download_images'),
            'summary'         => $transfer->summarize($payload),
        ];
        $request->session()->put(self::PENDING, $pending);

        $report = $transfer->import($payload, $data['sections'], true, $request->user());

        return redirect()->route('admin.transfer.index')
            ->with('transfer.report', $report)
            ->with('transfer.mode', 'preview');
    }

    /** Étape 2 : import réel du fichier simulé. */
    public function import(Request $request, ContentTransfer $transfer): RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);
        if (! $pending || ! Storage::disk('local')->exists($pending['path'])) {
            return redirect()->route('admin.transfer.index')->with('error', 'Aucun import en attente : téléversez de nouveau le fichier.');
        }

        $payload = json_decode(Storage::disk('local')->get($pending['path']), true);
        $report = $transfer->import($payload, $pending['sections'], false, $request->user(), $pending['download_images']);

        $this->discardPending($request);

        app(AuditTrail::class)->record('content.imported', meta: [
            'fichier'  => $pending['name'],
            'résultat' => collect($report)->reject(fn ($l, $k) => str_starts_with($k, '_'))
                ->map(fn ($l) => ['créés' => $l['created'], 'mis à jour' => $l['updated'], 'ignorés' => $l['skipped']])->all(),
        ]);

        return redirect()->route('admin.transfer.index')
            ->with('transfer.report', $report)
            ->with('transfer.mode', 'import')
            ->with('success', 'Import terminé.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->discardPending($request);

        return redirect()->route('admin.transfer.index')->with('success', 'Import annulé.');
    }

    protected function discardPending(Request $request): void
    {
        if ($pending = $request->session()->pull(self::PENDING)) {
            Storage::disk('local')->delete($pending['path']);
        }
    }

    protected function jsonDownload(array $data, string $filename): Response
    {
        return response(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type'        => 'application/json; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
    }
}
