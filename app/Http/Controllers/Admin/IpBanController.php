<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpBan;
use App\Services\LoginBan;
use App\Services\Supervision;
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

    public function store(Request $request, LoginBan $bans, Supervision $supervision): RedirectResponse
    {
        $data = $request->validate([
            'ip_address'   => ['required', 'ip'],
            'permanent'    => ['boolean'],
            'banned_until' => ['exclude_if:permanent,1', 'required', 'date', 'after:now'],
            'reason'       => ['nullable', 'string', 'max:255'],
            'http_status'  => $this->statusRules(),
        ], [
            'banned_until.after' => 'La date de fin doit être dans le futur.',
        ], [
            'http_status'  => 'code HTTP',
            'ip_address'   => 'adresse IP',
            'banned_until' => 'date de fin',
            'reason'       => 'motif',
        ]);

        $ip = inet_ntop(inet_pton($data['ip_address'])); // forme canonique (IPv6 abrégée, minuscules)
        $error = match (true) {
            $bans->isWhitelisted($ip)                 => 'Cette adresse figure dans la liste blanche (LOGIN_BAN_WHITELIST) : elle ne peut pas être bannie.',
            IpBan::active()->where('ip_address', $ip)->exists() => 'Cette adresse est déjà bannie : modifiez le bannissement en cours.',
            default                                   => null,
        };
        if ($error) {
            return back()->withErrors(['ip_address' => $error])->withInput();
        }

        // Sa propre adresse : on perd l'accès au site, d'où une validation superviseur (identifiant + PIN).
        $self = $ip === inet_ntop(inet_pton($request->ip()));
        if ($self && ! Supervision::granted($request, 'ip_bans.self')) {
            return $supervision->require($request, ['ip_bans.self'])
                ?? back()->withErrors(['ip_address' => 'Il s\'agit de votre adresse actuelle : la bannir exige la validation d\'un superviseur habilité à « Bannir sa propre adresse IP », et aucun ne l\'est.'])->withInput();
        }

        $bans->banManually(
            $ip,
            $request->boolean('permanent') ? null : Carbon::parse($data['banned_until']),
            $data['reason'] ?? null,
            $request->user(),
            isset($data['http_status']) ? (int) $data['http_status'] : null,
        );

        if ($self) {
            // L'accès est coupé dès la prochaine requête : pas de retour au panel.
            return redirect()->route('home');
        }

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
            'http_status'  => $this->statusRules(),
        ], [
            'banned_until.after' => 'La date de fin doit être dans le futur (pour lever le bannissement, utilisez « Lever »).',
        ], [
            'banned_until' => 'date de fin',
            'reason'       => 'motif',
            'http_status'  => 'code HTTP',
        ]);

        $bans->update(
            $ipBan,
            $request->boolean('permanent') ? null : Carbon::parse($data['banned_until']),
            $data['reason'] ?? null,
            $request->user(),
            isset($data['http_status']) ? (int) $data['http_status'] : null,
        );

        return redirect()->route('admin.ip-bans.index')->with('success', "Bannissement de {$ipBan->ip_address} modifié.");
    }

    /** @return list<mixed> */
    protected function statusRules(): array
    {
        return ['nullable', 'integer', \Illuminate\Validation\Rule::in(array_keys(IpBan::HTTP_STATUSES))];
    }

    public function destroy(Request $request, IpBan $ipBan, LoginBan $bans): RedirectResponse
    {
        if ($ipBan->isActive()) {
            $bans->lift($ipBan, $request->user());
        }

        return redirect()->route('admin.ip-bans.index')->with('success', "Bannissement de {$ipBan->ip_address} levé : l'adresse accède de nouveau au site.");
    }
}
