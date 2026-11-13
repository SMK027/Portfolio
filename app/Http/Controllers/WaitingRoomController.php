<?php

namespace App\Http\Controllers;

use App\Services\WaitingRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitingRoomController extends Controller
{
    /** Interrogé par la page d'attente : rang actuel, ou entrée sur le site. */
    public function status(Request $request, WaitingRoom $room): JsonResponse
    {
        $ticket = $room->find($request->cookie(WaitingRoom::COOKIE));

        if (! $room->isEnabled() || $ticket?->isAdmitted()) {
            return response()->json(['admitted' => true]);
        }

        if (! $ticket) {
            // Ticket expiré : la page se recharge pour en obtenir un nouveau.
            return response()->json(['admitted' => false, 'expired' => true]);
        }

        $room->touch($ticket);
        $room->advance();

        if ($ticket->refresh()->isAdmitted()) {
            return response()->json(['admitted' => true]);
        }

        $position = $room->position($ticket);

        return response()->json([
            'admitted' => false,
            'position' => $position,
            'minutes'  => $room->estimatedMinutes($position),
        ])->header('Cache-Control', 'no-store, private');
    }
}
