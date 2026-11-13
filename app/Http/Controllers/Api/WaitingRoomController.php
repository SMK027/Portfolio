<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WaitingRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitingRoomController extends Controller
{
    public function show(WaitingRoom $room): JsonResponse
    {
        return response()->json(['data' => $this->present($room)]);
    }

    public function update(Request $request, WaitingRoom $room): JsonResponse
    {
        // Champs facultatifs : les réglages non transmis sont conservés.
        $rules = array_map(fn (array $rule) => array_map(fn ($r) => $r === 'required' ? 'sometimes' : $r, $rule), WaitingRoom::rules());
        $data = $request->validate($rules);
        $room->update(WaitingRoom::normalize(array_merge($room->settings(), $data)));

        return response()->json(['data' => $this->present($room)]);
    }

    public function admit(WaitingRoom $room): JsonResponse
    {
        return response()->json(['data' => ['admitted' => $room->admitAll()] + $this->present($room)]);
    }

    protected function present(WaitingRoom $room): array
    {
        $room->prune();
        $settings = $room->settings();
        $status = $room->status();

        return array_merge($settings, $status, [
            'queueing_since' => $status['queueing_since']?->toIso8601String(),
        ]);
    }
}
