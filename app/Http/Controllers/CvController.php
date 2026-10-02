<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CV public : servi seulement si son téléchargement est autorisé. */
class CvController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $profile = Profile::current();
        abort_unless($profile->cvAvailable(), 404);

        return Storage::disk('local')->response($profile->cv_path, $profile->cvFilename(), [
            'Content-Type'  => 'application/pdf',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag'  => 'noindex',
        ], 'inline');
    }

    /** Aperçu dans le panel, que le téléchargement public soit autorisé ou non. */
    public function preview(): StreamedResponse
    {
        $profile = Profile::current();
        abort_unless($profile->cv_path && Storage::disk('local')->exists($profile->cv_path), 404);

        return Storage::disk('local')->response($profile->cv_path, $profile->cvFilename(), ['Content-Type' => 'application/pdf'], 'inline');
    }
}
