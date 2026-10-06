<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Point de téléversement de l'outil "image" d'Editor.js.
 * Réponse au format attendu : { success: 1, file: { url } }.
 */
class EditorUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => $validator->errors()->first('image')], 422);
        }

        $path = app(\App\Services\ImageOptimizer::class)->store($request->file('image'), 'editor/'.now()->format('Y/m'))['path'];
        app(\App\Services\AuditTrail::class)->record('editor.image_uploaded', meta: ['fichier' => '/storage/'.$path, 'nom' => $request->file('image')->getClientOriginalName()]);

        return response()->json([
            'success' => 1,
            // URL relative : le contenu reste valide si le domaine du site change.
            'file'    => ['url' => '/storage/'.$path],
        ]);
    }
}
