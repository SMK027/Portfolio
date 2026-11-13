<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WaitingRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WaitingRoomController extends Controller
{
    public function edit(WaitingRoom $room): View
    {
        $room->prune();

        return view('admin.waiting-room.edit', [
            'settings'  => $room->settings(),
            'endsAt'    => $room->endsAt(),
            'queueing'  => $room->isQueueing(),
            'admitted'  => $room->isEnabled() ? $room->admittedCount() : 0,
            'waiting'   => $room->isEnabled() ? $room->waitingCount() : 0,
            'since'     => $room->queueingSince(),
        ]);
    }

    public function update(Request $request, WaitingRoom $room): RedirectResponse
    {
        $data = $request->validate([
            'mode'            => ['required', Rule::in(array_keys(WaitingRoom::MODES))],
            'capacity'        => ['required', 'integer', 'min:1', 'max:100000'],
            'session_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'ends_at'         => ['nullable', 'date', 'after:now'],
            'message'         => ['nullable', 'string', 'max:1000'],
        ], [
            'ends_at.after' => 'La date de fin doit être dans le futur.',
        ], [
            'capacity'        => 'nombre de visiteurs simultanés',
            'session_minutes' => 'durée d\'inactivité',
            'ends_at'         => 'date de fin',
            'message'         => 'message',
        ]);

        $room->update([
            'mode'            => $data['mode'],
            'capacity'        => (int) $data['capacity'],
            'session_minutes' => (int) $data['session_minutes'],
            'ends_at'         => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at'])->toIso8601String() : null,
            'message'         => $data['message'] ?? null,
        ]);

        return back()->with('success', match ($data['mode']) {
            'off'    => 'File d\'attente désactivée.',
            'auto'   => 'File d\'attente automatique : elle se déclenchera si l\'affluence dépasse la capacité.',
            'manual' => 'File d\'attente activée pour tous les nouveaux visiteurs.',
        });
    }

    public function admitAll(WaitingRoom $room): RedirectResponse
    {
        $count = $room->admitAll();

        return back()->with('success', trans_choice('{0} Aucun visiteur en attente.|{1} 1 visiteur admis.|[2,*] :count visiteurs admis.', $count, ['count' => $count]));
    }
}
