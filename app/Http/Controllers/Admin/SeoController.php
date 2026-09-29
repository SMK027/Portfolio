<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Réglages de référencement : autoriser ou non l'indexation du site.
 */
class SeoController extends Controller
{
    public function edit(): View
    {
        return view('admin.seo.edit', ['indexable' => Setting::siteIsIndexable()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['indexable' => ['required', 'boolean']]);

        $indexable = $request->boolean('indexable');
        Setting::set(Setting::INDEXABLE, $indexable);

        return back()->with('success', $indexable
            ? 'Le site peut de nouveau être indexé par les moteurs de recherche.'
            : 'Le site est désindexé : les moteurs de recherche retireront progressivement ses pages.');
    }
}
