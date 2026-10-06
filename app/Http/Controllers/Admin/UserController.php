<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ServicePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Comptes humains : administrateurs, contributeurs (co-auteurs d'articles) et personnel
 * (autorisations limitées, désactivation programmable). La consultation est ouverte aux
 * admins, la modification aux super-admins. Un bot ou un membre du personnel autorisé
 * (users.write / users.delete) ne gère que les contributeurs.
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

        return view('admin.users.form', ['user' => new User(['global_role' => request()->user()->hasLimitedAccess() ? 'user' : 'admin', 'is_active' => true, 'permissions' => []])]);
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
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'username'       => ['required', 'string', 'alpha_dash', 'max:50', Rule::unique('users')->ignore($user)],
            'email'          => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            // Un bot ou un membre du personnel ne peut créer ou garder que des contributeurs.
            'global_role'    => ['required', Rule::in($request->user()->hasLimitedAccess() ? ['user'] : array_keys(User::ROLES))],
            'password'       => [$user->exists ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            // Personnel : autorisations, activation et désactivation programmée
            'permissions'    => ['nullable', 'array'],
            'permissions.*'  => ['string', Rule::in(ServicePermissions::all())],
            'is_active'      => ['nullable', 'boolean'],
            'deactivates_at' => ['nullable', 'date', Rule::when($request->boolean('is_active'), 'after:now')],
        ], [
            'deactivates_at.after' => 'La date de désactivation doit être dans le futur (ou décochez « Compte actif »).',
        ], [
            'deactivates_at' => 'date de désactivation',
        ]);

        // Les autres rôles ne sont ni limités ni désactivables.
        $staff = $data['global_role'] === 'staff';

        return [
            ...$data,
            'permissions'    => $staff ? array_values(array_unique($data['permissions'] ?? [])) : null,
            'is_active'      => $staff ? $request->boolean('is_active') : true,
            'deactivates_at' => $staff && filled($data['deactivates_at'] ?? null) ? Carbon::parse($data['deactivates_at']) : null,
        ];
    }
}
