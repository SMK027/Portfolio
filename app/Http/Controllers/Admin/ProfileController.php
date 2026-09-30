<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestion de la présentation affichée sur la page d'accueil.
 */
class ProfileController extends Controller
{
    use HandlesUploads;

    public function edit(): View
    {
        return view('admin.profile.edit', ['profile' => Profile::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = Profile::current();

        $data = $request->validate([
            'first_name'   => ['nullable', 'string', 'max:100'],
            'last_name'    => ['nullable', 'string', 'max:100'],
            'headline'     => ['nullable', 'string', 'max:255'],
            'location'     => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'social_links'        => ['nullable', 'array', 'max:20'],
            'social_links.*.name' => ['nullable', 'string', 'max:50'],
            'social_links.*.url'  => ['nullable', 'url:http,https', 'max:255'],
            'about'        => ['nullable', 'string', 'max:500000'],
            'photo'        => $this->imageRules(),
            'cv'           => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $data['about'] = $this->editorContent($request, 'about');
        // Les lignes sans URL sont ignorées.
        $data['social_links'] = collect($data['social_links'] ?? [])
            ->filter(fn ($link) => filled($link['url'] ?? null))
            ->map(fn ($link) => ['name' => trim((string) ($link['name'] ?? '')), 'url' => $link['url']])
            ->values()
            ->all();
        $data['photo_path'] = $this->syncPublicFile($request, 'photo', $profile->photo_path, 'profile');
        $data['cv_path'] = $this->syncPublicFile($request, 'cv', $profile->cv_path, 'profile');
        unset($data['photo'], $data['cv']);

        $profile->update($data);

        return back()->with('success', 'Présentation mise à jour.');
    }
}
