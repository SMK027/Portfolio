<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Maintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MaintenanceController extends Controller
{
    public function show(Maintenance $maintenance): JsonResponse
    {
        return response()->json(['data' => $this->present($maintenance)]);
    }

    public function update(Request $request, Maintenance $maintenance): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:now', ...($request->filled('starts_at') ? ['after:starts_at'] : [])],
            'reason'  => ['nullable', 'string', 'max:1000'],
        ]);

        $data['enabled']
            ? $maintenance->enable(filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null, $data['reason'] ?? null, filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at']) : null)
            : $maintenance->disable();

        return response()->json(['data' => $this->present($maintenance)]);
    }

    protected function present(Maintenance $maintenance): array
    {
        return [
            'enabled' => $maintenance->isActive(),
            'scheduled' => $maintenance->isScheduled(),
            'starts_at' => $maintenance->isScheduled() ? $maintenance->startsAt()->toIso8601String() : null,
            'ends_at' => $maintenance->isActive() || $maintenance->isScheduled() ? $maintenance->endsAt()?->toIso8601String() : null,
            'reason'  => $maintenance->isActive() || $maintenance->isScheduled() ? $maintenance->reason() : null,
        ];
    }
}
