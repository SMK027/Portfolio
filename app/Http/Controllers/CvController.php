<?php

namespace App\Http\Controllers;

use App\Models\CvAccessCode;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CV public : servi seulement si son téléchargement est autorisé et sur
 * présentation d'un code d'accès valide (voir CvAccessCode).
 */
class CvController extends Controller
{
    /** Formulaire de saisie du code (prérempli par le lien partagé). */
    public function __invoke(Request $request): View
    {
        abort_unless(Profile::current()->cvAvailable(), 404);

        return view('public.cv', ['code' => substr((string) $request->query('code'), 0, 32)]);
    }

    public function download(Request $request): StreamedResponse|RedirectResponse
    {
        $profile = Profile::current();
        abort_unless($profile->cvAvailable(), 404);

        $request->validate([
            'code'    => ['required', 'string', 'max:32'],
            'website' => ['prohibited'], // champ piège anti-robot
        ]);

        $code = CvAccessCode::findUsable($request->input('code'));
        if (! $code) {
            return back()->withErrors(['code' => 'Ce code est invalide, a expiré ou a déjà été utilisé le nombre de fois prévu.'])->withInput();
        }

        $code->markUsed();

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
