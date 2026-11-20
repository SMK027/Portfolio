<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Intitulés, ordre et statut (publique, protégée, maintenance, brouillon…) des pages du site.
 */
class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', ['pages' => Page::ordered()->get()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'pages'             => ['required', 'array'],
            'pages.*.title'     => ['required', 'string', 'max:100'],
            'pages.*.intro'     => ['nullable', 'string', 'max:500'],
            'pages.*.position'  => ['required', 'integer', 'min:0', 'max:999'],
            'pages.*.status'    => ['required', 'in:'.implode(',', array_keys(Page::STATUSES))],
            'pages.*.password'  => ['nullable', 'string', 'min:4', 'max:100'],
        ], [], ['pages.*.title' => 'titre', 'pages.*.position' => 'ordre', 'pages.*.status' => 'statut', 'pages.*.password' => 'mot de passe']);

        // Protection activée sans mot de passe existant : mot de passe obligatoire
        $errors = [];
        foreach (Page::all() as $page) {
            $input = $request->input("pages.{$page->id}");
            if (is_array($input) && ($input['status'] ?? null) === Page::PASSWORD && ! $page->password && blank($input['password'] ?? null)) {
                $errors["pages.{$page->id}.password"] = 'Le mot de passe est obligatoire pour protéger la page.';
            }
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        foreach (Page::all() as $page) {
            $input = $request->input("pages.{$page->id}");

            if (! is_array($input)) {
                continue;
            }

            $page->fill([
                'title'    => $input['title'],
                'intro'    => $input['intro'] ?? null,
                'position' => (int) $input['position'],
                'status'   => $input['status'],
            ]);
            // Mot de passe : effacé hors protection, vide = inchangé
            if ($page->status !== Page::PASSWORD) {
                $page->password = null;
            } elseif (filled($input['password'] ?? null)) {
                $page->password = $input['password'];
            }
            $page->save();
        }

        return back()->with('success', 'Pages mises à jour.');
    }
}
