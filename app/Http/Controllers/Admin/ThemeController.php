<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeController extends Controller
{
    use HandlesUploads;

    public function index(): View
    {
        return view('admin.themes.index', [
            'themes' => Theme::ordered()->withCount('projects', 'articles')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.themes.form', ['theme' => new Theme(['position' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $theme = new Theme;
        $theme->fill($this->validated($request, $theme))->save();

        return redirect()->route('admin.themes.index')->with('success', 'Thème ajouté.');
    }

    public function edit(Theme $theme): View
    {
        return view('admin.themes.form', ['theme' => $theme]);
    }

    public function update(Request $request, Theme $theme): RedirectResponse
    {
        $theme->update($this->validated($request, $theme));

        return redirect()->route('admin.themes.index')->with('success', 'Thème mis à jour.');
    }

    public function destroy(Theme $theme): RedirectResponse
    {
        $this->deletePublicFile($theme->background_path);
        $theme->delete();

        return back()->with('success', 'Thème supprimé.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, Theme $theme): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
            'background'  => $this->imageRules(8192),
        ]);

        $data['background_path'] = $this->syncPublicFile($request, 'background', $theme->background_path, 'themes');
        $data['position'] = (int) ($data['position'] ?? 0);
        unset($data['background']);

        return $data;
    }
}
