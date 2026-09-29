<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Intitulés, ordre et visibilité (publique / privée) des pages du site.
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
            'pages.*.is_public' => ['nullable', 'boolean'],
        ], [], ['pages.*.title' => 'titre', 'pages.*.position' => 'ordre']);

        foreach (Page::all() as $page) {
            $input = $request->input("pages.{$page->id}");

            if (! is_array($input)) {
                continue;
            }

            $page->update([
                'title'     => $input['title'],
                'intro'     => $input['intro'] ?? null,
                'position'  => (int) $input['position'],
                'is_public' => (bool) ($input['is_public'] ?? false),
            ]);
        }

        return back()->with('success', 'Pages mises à jour.');
    }
}
