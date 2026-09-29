<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Envoie une pièce jointe (modèle utilisant IsAttachment) avec des en-têtes
 * de sécurité : seules les images et les vrais PDF s'affichent dans le
 * navigateur, le reste est téléchargé.
 */
trait ServesAttachments
{
    protected function attachmentResponse(Request $request, Model $file): StreamedResponse
    {
        $disk = Storage::disk($file::DISK);
        abort_unless($disk->exists($file->path), 404);

        $inline = ! $request->boolean('download') && ($file->is_image || $file->isPdf());

        $headers = [
            'Content-Type'            => $file->mime_type,
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
            'Cache-Control'           => 'private, max-age=86400',
        ];

        return $inline
            ? $disk->response($file->path, $file->original_name, $headers)
            : $disk->download($file->path, $file->original_name, $headers);
    }
}
