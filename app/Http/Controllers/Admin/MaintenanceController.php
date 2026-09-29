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
        ]);
    }

    public function update(Request $request, Maintenance $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'reason'  => ['nullable', 'string', 'max:1000'],
        ], [
            'ends_at.after' => 'La date de fin doit être dans le futur.',
        ], [
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
        );

        return back()->with('success', 'Maintenance activée : les visiteurs voient la page de maintenance.');
    }
}
