<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LoginPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Adresse de la page de connexion (super-administrateurs) : voir App\Services\LoginPath.
 */
class LoginPathController extends Controller
{
    public function edit(): View
    {
        return view('admin.login-path.edit', [
            'path'   => LoginPath::current(),
            'custom' => LoginPath::isCustom(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'path'             => ['nullable', 'string', 'max:100'],
            'current_password' => ['required', 'current_password'],
        ], [], [
            'path'             => 'adresse de connexion',
            'current_password' => 'mot de passe actuel',
        ]);

        // Champ vide : retour à /login
        $path = LoginPath::normalize($request->input('path')) ?: LoginPath::DEFAULT;

        if ($problem = LoginPath::problem($path)) {
            throw ValidationException::withMessages(['path' => $problem]);
        }

        if ($path === LoginPath::configured()) {
            return back()->with('success', 'Adresse de connexion inchangée.');
        }

        LoginPath::set($path);

        return redirect()->route('admin.login-path.edit')->with('success', $path === LoginPath::DEFAULT
            ? 'Adresse de connexion rétablie : '.url(LoginPath::DEFAULT)
            : 'Nouvelle adresse de connexion : '.url($path).' — enregistrez-la dans vos favoris, /login ne répond plus.');
    }
}
