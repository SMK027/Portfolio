<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ValidatesPreciseDates;
use App\Http\Controllers\Controller;
use App\Models\Diploma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiplomaController extends Controller
{
    use ValidatesPreciseDates;

    public function index(): View
    {
        return view('admin.diplomas.index', ['diplomas' => Diploma::ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.diplomas.form', ['diploma' => new Diploma(['position' => 0, 'date_precision' => 'year'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Diploma::create($this->validated($request));

        return redirect()->route('admin.diplomes.index')->with('success', 'Diplôme ajouté.');
    }

    public function edit(Diploma $diploma): View
    {
        return view('admin.diplomas.form', ['diploma' => $diploma]);
    }

    public function update(Request $request, Diploma $diploma): RedirectResponse
    {
        $diploma->update($this->validated($request));

        return redirect()->route('admin.diplomes.index')->with('success', 'Diplôme mis à jour.');
    }

    public function destroy(Diploma $diploma): RedirectResponse
    {
        $diploma->delete();

        return back()->with('success', 'Diplôme supprimé.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'level'       => ['nullable', 'string', 'max:50'],
            'mention'     => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $dates = $this->preciseDates($request, ['obtained_at' => true]);

        return ['position' => (int) ($data['position'] ?? 0)] + $dates + $data;
    }
}
