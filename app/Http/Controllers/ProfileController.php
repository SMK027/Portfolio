<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        abort_if($request->user()->isMachine(), 403, 'Les comptes techniques n\'ont pas de page « Mon compte ».');

        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        abort_if($request->user()->isMachine(), 403, 'Les comptes techniques n\'ont pas de page « Mon compte ».');

        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort_if($request->user()->isMachine(), 403, 'Les comptes techniques n\'ont pas de page « Mon compte ».');

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->articles()->exists()) {
            return Redirect::route('profile.edit')->withErrors(
                ['password' => 'Vous êtes l\'auteur principal d\'articles : réattribuez-les avant de supprimer votre compte.'],
                'userDeletion'
            );
        }

        if ($user->isSuperAdmin() && User::where('global_role', 'superadmin')->count() === 1) {
            return Redirect::route('profile.edit')->withErrors(
                ['password' => 'Vous êtes le dernier super-administrateur : ce compte ne peut pas être supprimé.'],
                'userDeletion'
            );
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
