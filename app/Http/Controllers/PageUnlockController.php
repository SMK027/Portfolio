<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Déverrouillage d'une page protégée par mot de passe : retour à l'adresse demandée. */
class PageUnlockController extends Controller
{
    public function __invoke(Request $request, string $key): RedirectResponse
    {
        $page = Page::findByKey($key);
        abort_unless($page?->status === Page::PASSWORD && $page->password, 404);
        $request->validate(['password' => ['required', 'string', 'max:100']]);

        if ($error = $page->attemptUnlock($request->input('password'), $request->ip())) {
            throw ValidationException::withMessages(['password' => $error]);
        }

        return redirect()->back(fallback: $page->url());
    }
}
