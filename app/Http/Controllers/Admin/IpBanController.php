<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpBan;
use App\Services\LoginBan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Adresses IP bannies (super-administrateurs) : bannissements automatiques après
 * des échecs de connexion et bannissements manuels ; consultation, modification
 * de la date de fin et levée anticipée.
 */
class IpBanController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.ip-bans.index', [
            'active'  => IpBan::active()->latest()->get(),
            'history' => IpBan::with('liftedBy')
                ->where(fn ($q) => $q->whereNotNull('lifted_at')->orWhere('banned_until', '<=', now()))
                ->latest()->limit(50)->get(),
            'myIp'    => $request->ip(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.ip-bans.create', ['ip' => (string) $request->query('ip', '')]);
    }

    public function store(Request $request, LoginBan $bans): RedirectResponse
    {
        $data = $request->validate([
            'ip_address'   => ['required', 'ip'],
            'permanent'    => ['boolean'],
            'banned_until' => ['exclude_if:permanent,1', 'required', 'date', 'after:now'],
            'reason'       => ['nullable', 'string', 'max:255'],
        ], [
            'banned_until.after' => 'La date de fin doit être dans le futur.',
        ], [
            'ip_address'   => 'adresse IP',
            'banned_until' => 'date de fin',
            'reason'       => 'motif',
        ]);

        $ip = inet_ntop(inet_pton($data['ip_address'])); // forme canonique (IPv6 abrégée, minuscules)
        $error = match (true) {
            $ip === $request->ip()                    => 'Il s\'agit de votre adresse actuelle : vous perdriez l\'accès au site.',
            $bans->isWhitelisted($ip)                 => 'Cette adresse figure dans la liste blanche (LOGIN_BAN_WHITELIST) : elle ne peut pas être bannie.',
            IpBan::active()->where('ip_address', $ip)->exists() => 'Cette adresse est déjà bannie : modifiez le bannissement en cours.',
            default                                   => null,
        };
        if ($error) {
            return back()->withErrors(['ip_address' => $error])->withInput();
        }

        $bans->banManually(
            $ip,
            $request->boolean('permanent') ? null : Carbon::parse($data['banned_until']),
            $data['reason'] ?? null,
            $request->user(),
        );

        return redirect()->route('admin.ip-bans.index')->with('success', "{$ip} est bannie du site.");
    }

    public function edit(IpBan $ipBan): View
    {
        abort_unless($ipBan->isActive(), 404);

        return view('admin.ip-bans.edit', ['ban' => $ipBan]);
    }

    public function update(Request $request, IpBan $ipBan, LoginBan $bans): RedirectResponse
    {
        abort_unless($ipBan->isActive(), 404);

        $data = $request->validate([
            'permanent'    => ['boolean'],
            'banned_until' => ['exclude_if:permanent,1', 'required', 'date', 'after:now'],
            'reason'       => ['nullable', 'string', 'max:255'],
        ], [
            'banned_until.after' => 'La date de fin doit être dans le futur (pour lever le bannissement, utilisez « Lever »).',
        ], [
            'banned_until' => 'date de fin',
            'reason'       => 'motif',
        ]);

        $bans->update(
            $ipBan,
            $request->boolean('permanent') ? null : Carbon::parse($data['banned_until']),
            $data['reason'] ?? null,
            $request->user(),
        );

        return redirect()->route('admin.ip-bans.index')->with('success', "Bannissement de {$ipBan->ip_address} modifié.");
    }

    public function destroy(Request $request, IpBan $ipBan, LoginBan $bans): RedirectResponse
    {
        if ($ipBan->isActive()) {
            $bans->lift($ipBan, $request->user());
        }

        return redirect()->route('admin.ip-bans.index')->with('success', "Bannissement de {$ipBan->ip_address} levé : l'adresse accède de nouveau au site.");
    }
}
