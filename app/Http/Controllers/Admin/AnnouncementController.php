<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', ['announcements' => Announcement::ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.announcements.form', ['announcement' => new Announcement([
            'style'          => 'primary',
            'is_active'      => true,
            'is_dismissible' => true,
            'position'       => 0,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Announcement::create($this->validated($request));

        return redirect()->route('admin.annonces.index')->with('success', 'Annonce créée.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', ['announcement' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request));

        return redirect()->route('admin.annonces.index')->with('success', 'Annonce mise à jour.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Annonce supprimée.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:150'],
            'message'        => ['nullable', 'string', 'max:500'],
            'style'          => ['required', Rule::in(array_keys(Announcement::STYLES))],
            'link_url'       => ['nullable', 'url:http,https', 'max:2048', 'required_with:link_label'],
            'link_label'     => ['nullable', 'string', 'max:60'],
            'is_active'      => ['nullable', 'boolean'],
            'is_dismissible' => ['nullable', 'boolean'],
            'starts_at'      => ['nullable', 'date'],
            'ends_at'        => ['nullable', 'date', 'after:starts_at'],
            'position'       => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return [
            ...$data,
            'is_active'      => $request->boolean('is_active'),
            'is_dismissible' => $request->boolean('is_dismissible'),
            'position'       => (int) ($data['position'] ?? 0),
        ];
    }
}
