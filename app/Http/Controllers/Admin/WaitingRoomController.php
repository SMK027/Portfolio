<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WaitingRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WaitingRoomController extends Controller
{
    public function edit(WaitingRoom $room): View
    {
        $room->prune();

        return view('admin.waiting-room.edit', [
            'settings' => $room->settings(),
            'endsAt'   => $room->endsAt(),
            'status'   => $room->status(),
        ]);
    }

    public function update(Request $request, WaitingRoom $room): RedirectResponse
    {
        $data = $request->validate(WaitingRoom::rules(), [
            'ends_at.after' => 'La date de fin doit être dans le futur.',
        ], [
            'capacity'        => 'nombre de visiteurs simultanés',
            'session_minutes' => 'durée d\'inactivité',
            'ends_at'         => 'date de fin',
            'message'         => 'message',
            'max_response_ms' => 'temps de réponse maximal',
            'max_load'        => 'charge maximale',
        ]);

        $room->update(WaitingRoom::normalize($data));

        return back()->with('success', match ($data['mode']) {
            'off'    => 'File d\'attente désactivée.',
            'auto'   => 'File d\'attente automatique : elle se déclenchera si l\'affluence ou la charge dépasse les seuils.',
            'manual' => 'File d\'attente activée pour tous les nouveaux visiteurs.',
        });
    }

    public function admitAll(WaitingRoom $room): RedirectResponse
    {
        $count = $room->admitAll();

        return back()->with('success', trans_choice('{0} Aucun visiteur en attente.|{1} 1 visiteur admis.|[2,*] :count visiteurs admis.', $count, ['count' => $count]));
    }
}
