<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CountryBlock;
use App\Services\GeoIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Liste noire de pays (super-administrateurs) : voir App\Services\CountryBlock.
 */
class BlockedCountryController extends Controller
{
    public function edit(Request $request, CountryBlock $block): View
    {
        return view('admin.blocked-countries.edit', [
            'available' => CountryBlock::available(),
            'blocked'   => CountryBlock::countries(),
            'myCountry' => $block->countryOf($request),
            'geoipReady' => is_file(GeoIp::path('city')) || is_file(GeoIp::path('country')),
        ]);
    }

    public function update(Request $request, CountryBlock $block): RedirectResponse
    {
        $available = array_keys(CountryBlock::available());
        $data = $request->validate([
            'countries'   => ['array'],
            'countries.*' => ['string', Rule::in($available)],
        ], [], ['countries' => 'pays', 'countries.*' => 'pays']);

        $countries = $data['countries'] ?? [];

        // Ne pas s'enfermer dehors : le pays de l'administrateur ne peut être bloqué.
        $mine = $block->countryOf($request);
        if ($mine && in_array($mine, $countries, true) && ! app(\App\Services\LoginBan::class)->isWhitelisted($request->ip())) {
            throw ValidationException::withMessages(['countries' => 'Vous ne pouvez pas bloquer votre propre pays ('.GeoIp::label($mine).') : vous perdriez l\'accès au site.']);
        }

        CountryBlock::set($countries);

        return back()->with('success', $countries
            ? count($countries).' pays bloqué(s).'
            : 'Liste noire vidée : aucun pays n\'est bloqué.');
    }
}
