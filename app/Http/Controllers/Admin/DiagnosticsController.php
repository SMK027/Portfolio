<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Diagnostics;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/** Diagnostic en temps réel (super-administrateurs) : la page interroge l'état toutes les quelques secondes. */
class DiagnosticsController extends Controller
{
    public function show(Diagnostics $diagnostics): View
    {
        return view('admin.diagnostics', ['initial' => $diagnostics->run()]);
    }

    public function data(Diagnostics $diagnostics): JsonResponse
    {
        return response()->json($diagnostics->run())->header('Cache-Control', 'no-store');
    }
}
