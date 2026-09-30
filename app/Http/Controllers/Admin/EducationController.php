<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ValidatesPreciseDates;
use App\Http\Controllers\Controller;
use App\Models\Education;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EducationController extends Controller
{
    use ValidatesPreciseDates;

    public function index(): View
    {
        return view('admin.educations.index', ['educations' => Education::ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.educations.form', ['education' => new Education(['position' => 0, 'date_precision' => 'year'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Education::create($this->validated($request));

        return redirect()->route('admin.formations.index')->with('success', 'Formation ajoutée.');
    }

    public function edit(Education $education): View
    {
        return view('admin.educations.form', ['education' => $education]);
    }

    public function update(Request $request, Education $education): RedirectResponse
    {
        $education->update($this->validated($request));

        return redirect()->route('admin.formations.index')->with('success', 'Formation mise à jour.');
    }

    public function destroy(Education $education): RedirectResponse
    {
        $education->delete();

        return back()->with('success', 'Formation supprimée.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'location'    => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // « En cours » : pas de date de fin.
        $dates = $this->preciseDates($request, [
            'start_date' => true,
            'end_date'   => false,
        ], ['start_date', 'end_date']);

        if ($request->boolean('ongoing')) {
            $dates['end_date'] = null;
        }

        return ['position' => (int) ($data['position'] ?? 0)] + $dates + $data;
    }
}
