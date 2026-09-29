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
            'github_url'   => ['nullable', 'url:https', 'max:255'],
            'linkedin_url' => ['nullable', 'url:https', 'max:255'],
            'website_url'  => ['nullable', 'url:http,https', 'max:255'],
            'about'        => ['nullable', 'string', 'max:500000'],
            'photo'        => $this->imageRules(),
            'cv'           => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $data['about'] = $this->editorContent($request, 'about');
        $data['photo_path'] = $this->syncPublicFile($request, 'photo', $profile->photo_path, 'profile');
        $data['cv_path'] = $this->syncPublicFile($request, 'cv', $profile->cv_path, 'profile');
        unset($data['photo'], $data['cv']);

        $profile->update($data);

        return back()->with('success', 'Présentation mise à jour.');
    }
}
