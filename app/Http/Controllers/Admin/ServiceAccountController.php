<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceToken;
use App\Models\User;
use App\Support\ServicePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Comptes de service (API) et bots (connexion au panel) : création,
 * autorisations et codes d'application.
 * Réservé aux super-administrateurs.
 */
class ServiceAccountController extends Controller
{
    public function index(): View
    {
        return view('admin.service-accounts.index', [
            'accounts' => User::whereIn('global_role', ['service', 'bot'])
                ->withCount(['serviceTokens as active_tokens_count' => fn ($q) => $q->whereNull('disabled_at')])
                ->withMax('serviceTokens as last_used_at', 'last_used_at')
                ->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.service-accounts.form', ['account' => new User(['is_active' => true, 'permissions' => [], 'global_role' => 'service'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $type = $request->validate(['type' => ['required', Rule::in(['service', 'bot'])]])['type'];
        $base = ($type === 'bot' ? 'bot-' : 'svc-').(Str::slug($data['name']) ?: 'compte');
        $username = $base;
        for ($i = 2; User::where('username', $username)->exists(); $i++) {
            $username = $base.'-'.$i;
        }

        $account = User::create([
            ...$data,
            'username'          => $username,
            'email'             => $username.'@service.invalid',
            'password'          => Str::random(64), // jamais utilisé : connexion par mot de passe refusée
            'global_role'       => $type,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.service-accounts.show', $account)
            ->with('success', ($type === 'bot' ? 'Bot' : 'Compte de service').' créé. Générez maintenant un code d\'application.');
    }

    public function show(User $serviceAccount): View
    {
        $this->ensureService($serviceAccount);

        return view('admin.service-accounts.show', [
            'account'  => $serviceAccount,
            'tokens'   => $serviceAccount->serviceTokens()->with('creator')->get(),
            'newToken' => session('service_token'),
        ]);
    }

    public function edit(User $serviceAccount): View
    {
        $this->ensureService($serviceAccount);

        return view('admin.service-accounts.form', ['account' => $serviceAccount]);
    }

    public function update(Request $request, User $serviceAccount): RedirectResponse
    {
        $this->ensureService($serviceAccount);
        $serviceAccount->update($this->validated($request));

        return redirect()->route('admin.service-accounts.show', $serviceAccount)->with('success', 'Compte de service mis à jour.');
    }

    public function destroy(User $serviceAccount): RedirectResponse
    {
        $this->ensureService($serviceAccount);

        if ($serviceAccount->articles()->exists()) {
            return back()->with('error', 'Ce compte est l\'auteur d\'articles : désactivez-le plutôt que de le supprimer.');
        }

        $serviceAccount->serviceTokens()->get()->each->delete();
        $serviceAccount->delete();

        return redirect()->route('admin.service-accounts.index')->with('success', 'Compte de service supprimé ; ses codes ne fonctionnent plus.');
    }

    /** Nouveau code d'application : affiché une seule fois. */
    public function issueToken(Request $request, User $serviceAccount): RedirectResponse
    {
        $this->ensureService($serviceAccount);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'intitulé']);

        [$token, $plain] = ServiceToken::issue($serviceAccount, $data['name'], $request->user());

        return redirect()->route('admin.service-accounts.show', $serviceAccount)
            ->with('service_token', ['id' => $token->id, 'name' => $token->name, 'plain' => $plain]);
    }

    /** Active ou désactive un code : effet immédiat (API et sessions de bot). */
    public function toggleToken(User $serviceAccount, ServiceToken $token): RedirectResponse
    {
        $this->ensureService($serviceAccount);
        abort_unless($token->user_id === $serviceAccount->id, 404);

        $token->update(['disabled_at' => $token->isDisabled() ? null : now()]);

        return back()->with('success', 'Code « '.$token->name.' » '.($token->isDisabled() ? 'désactivé : il ne fonctionne plus.' : 'réactivé.'));
    }

    /** Suppression définitive (code compromis). */
    public function destroyToken(User $serviceAccount, ServiceToken $token): RedirectResponse
    {
        $this->ensureService($serviceAccount);
        abort_unless($token->user_id === $serviceAccount->id, 404);

        $token->delete();

        return back()->with('success', 'Code « '.$token->name.' » supprimé définitivement.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'description'   => ['nullable', 'string', 'max:500'],
            'is_active'     => ['nullable', 'boolean'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => [Rule::in(ServicePermissions::all())],
        ]);

        return [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => $request->boolean('is_active'),
            'permissions' => array_values(array_intersect(ServicePermissions::all(), $data['permissions'] ?? [])),
        ];
    }

    protected function ensureService(User $user): void
    {
        abort_unless($user->isMachine(), 404);
    }
}
