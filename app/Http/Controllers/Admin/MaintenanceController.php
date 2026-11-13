<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Maintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function edit(Maintenance $maintenance): View
    {
        return view('admin.maintenance.edit', [
            'active' => $maintenance->isActive(),
            'endsAt' => $maintenance->endsAt(),
            'reason' => $maintenance->reason(),
            'startsAt' => $maintenance->isScheduled() ? $maintenance->startsAt() : null,
        ]);
    }

    public function update(Request $request, Maintenance $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:now', ...($request->filled('starts_at') ? ['after:starts_at'] : [])],
            'reason'  => ['nullable', 'string', 'max:1000'],
        ], [
            'ends_at.after' => 'La date de fin doit être dans le futur et après le début.',
            'starts_at.after' => 'Le début programmé doit être dans le futur.',
        ], [
            'starts_at' => 'début programmé',
            'ends_at' => 'date de fin',
            'reason'  => 'motif',
        ]);

        if (! $request->boolean('enabled')) {
            $maintenance->disable();

            return back()->with('success', 'Maintenance désactivée : le site est de nouveau accessible à tous.');
        }

        $maintenance->enable(
            filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null,
            $data['reason'] ?? null,
            filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at']) : null,
        );

        return back()->with('success', $maintenance->isScheduled()
            ? 'Maintenance programmée le '.$maintenance->startsAt()->translatedFormat('j F Y à H:i').'.'
            : 'Maintenance activée : les visiteurs voient la page de maintenance.');
    }
}
