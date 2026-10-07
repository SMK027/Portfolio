<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supervisor;
use App\Models\User;
use App\Support\ServicePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Superviseurs (super-administrateurs uniquement). */
class SupervisorController extends Controller
{
    public function index(): View
    {
        return view('admin.supervisors.index', ['supervisors' => Supervisor::with('user')->orderBy('username')->get()]);
    }

    public function create(): View
    {
        return view('admin.supervisors.form', ['supervisor' => new Supervisor(['is_active' => true, 'permissions' => []]), 'admins' => $this->admins()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supervisor = new Supervisor;
        $this->save($request, $supervisor);

        return redirect()->route('admin.supervisors.index')->with('success', 'Superviseur « '.$supervisor->username.' » créé.');
    }

    public function edit(Supervisor $supervisor): View
    {
        return view('admin.supervisors.form', ['supervisor' => $supervisor, 'admins' => $this->admins()]);
    }

    public function update(Request $request, Supervisor $supervisor): RedirectResponse
    {
        $this->save($request, $supervisor);

        return redirect()->route('admin.supervisors.index')->with('success', 'Superviseur « '.$supervisor->username.' » mis à jour.');
    }

    public function destroy(Supervisor $supervisor): RedirectResponse
    {
        $supervisor->delete();

        return back()->with('success', 'Superviseur supprimé.');
    }

    protected function save(Request $request, Supervisor $supervisor): void
    {
        // Réattribution à un autre administrateur : nouveau PIN obligatoire (l'ancien titulaire le connaît).
        $reassigned = $supervisor->exists && (int) $request->input('user_id') !== $supervisor->user_id;

        $data = $request->validate([
            'username'      => ['required', 'string', 'alpha_dash', 'max:50', Rule::unique('supervisors')->ignore($supervisor)],
            'user_id'       => ['required', Rule::exists('users', 'id')->whereIn('global_role', ['admin', 'superadmin'])],
            'pin'           => [! $supervisor->exists || $reassigned ? 'required' : 'nullable', 'digits_between:4,8', 'confirmed'],
            'is_active'     => ['nullable', 'boolean'],
            'permissions'   => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::in(ServicePermissions::all())],
        ], [
            'pin.required'        => $reassigned ? 'Réattribution : définissez un nouveau code PIN.' : 'Le code PIN est obligatoire.',
            'permissions.required' => 'Choisissez au moins une opération que ce superviseur peut valider.',
        ], ['username' => 'identifiant', 'user_id' => 'administrateur rattaché', 'pin' => 'code PIN']);

        $supervisor->fill([
            'username'    => $data['username'],
            'user_id'     => (int) $data['user_id'],
            'is_active'   => $request->boolean('is_active'),
            'permissions' => array_values(array_intersect(ServicePermissions::all(), $data['permissions'])),
        ]);
        if (filled($data['pin'] ?? null)) {
            $supervisor->setPin($data['pin']);
        }
        $supervisor->save();
    }

    protected function admins()
    {
        return User::whereIn('global_role', ['admin', 'superadmin'])->orderBy('name')->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name.' ('.$u->roleLabel().')'.($u->isActive() ? '' : ' — désactivé')]);
    }
}
