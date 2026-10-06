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
 * Adresses IP bannies après des échecs de connexion (super-administrateurs) :
 * consultation, modification de la date de fin et levée anticipée.
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
