<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Comptes : administrateurs et contributeurs (co-auteurs d'articles).
 * La consultation est ouverte aux admins, la modification aux super-admins.
 * Un bot autorisé (users.write / users.delete) ne gère que les contributeurs.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::humans()->withCount('articles')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-users');

        return view('admin.users.form', ['user' => new User(['global_role' => request()->user()->isBot() ? 'user' : 'admin'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $this->validated($request, new User);
        $data['email_verified_at'] = now();

        User::create($data);

        return redirect()->route('admin.utilisateurs.index')->with('success', 'Compte créé.');
    }

    public function edit(User $user): View
    {
        abort_if($user->isMachine(), 404); // géré dans « Comptes de service et bots »
        Gate::authorize('manage-users', $user);

        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isMachine(), 404); // géré dans « Comptes de service et bots »
        Gate::authorize('manage-users', $user);

        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && $user->isSuperAdmin() && $data['global_role'] !== 'superadmin') {
            return back()->withErrors(['global_role' => 'Vous ne pouvez pas retirer votre propre rôle de super-administrateur.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.utilisateurs.index')->with('success', 'Compte mis à jour.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isMachine(), 404); // géré dans « Comptes de service et bots »
        Gate::authorize('manage-users', [$user, 'users.delete']);

        if ($user->is($request->user())) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte ici.');
        }

        if ($user->articles()->exists()) {
            return back()->with('error', 'Ce compte est l\'auteur principal d\'articles : réattribuez-les avant de le supprimer.');
        }

        $user->delete();

        return back()->with('success', 'Compte supprimé.');
    }

    /** Retire tous les seconds facteurs d'un compte (perte des clés et codes de secours). */
    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($user->isMachine(), 404);

        $user->resetTwoFactor();
        app(\App\Services\AuditTrail::class)->record('two_factor.reset', $user, force: true);

        return back()->with('success', 'Double authentification réinitialisée pour « '.$user->name.' ».');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, User $user): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'username'    => ['required', 'string', 'alpha_dash', 'max:50', Rule::unique('users')->ignore($user)],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            // Un bot ne peut créer ou garder que des contributeurs.
            'global_role' => ['required', Rule::in($request->user()->isBot() ? ['user'] : array_keys(User::ROLES))],
            'password'    => [$user->exists ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ]);
    }
}
