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
            'ends_at' => ['nullable', 'date', 'after:now'],
            'reason'  => ['nullable', 'string', 'max:1000'],
        ]);

        $data['enabled']
            ? $maintenance->enable(filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null, $data['reason'] ?? null)
            : $maintenance->disable();

        return response()->json(['data' => $this->present($maintenance)]);
    }

    protected function present(Maintenance $maintenance): array
    {
        return [
            'enabled' => $maintenance->isActive(),
            'ends_at' => $maintenance->isActive() ? $maintenance->endsAt()?->toIso8601String() : null,
            'reason'  => $maintenance->isActive() ? $maintenance->reason() : null,
        ];
    }
}
