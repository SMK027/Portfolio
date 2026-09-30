<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesRichText;
use App\Http\Controllers\Admin\Concerns\ValidatesPreciseDates;
use App\Http\Controllers\Controller;
use App\Models\Experience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExperienceController extends Controller
{
    use HandlesRichText, ValidatesPreciseDates;

    public function index(): View
    {
        return view('admin.experiences.index', ['experiences' => Experience::ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.experiences.form', ['experience' => new Experience(['position' => 0, 'date_precision' => 'month'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Experience::create($this->validated($request));

        return redirect()->route('admin.experiences.index')->with('success', 'Expérience ajoutée.');
    }

    public function edit(Experience $experience): View
    {
        return view('admin.experiences.form', ['experience' => $experience]);
    }

    public function update(Request $request, Experience $experience): RedirectResponse
    {
        $experience->update($this->validated($request));

        return redirect()->route('admin.experiences.index')->with('success', 'Expérience mise à jour.');
    }

    public function destroy(Experience $experience): RedirectResponse
    {
        $experience->delete();

        return back()->with('success', 'Expérience supprimée.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'company'       => ['required', 'string', 'max:255'],
            'location'      => ['nullable', 'string', 'max:255'],
            'contract_type' => ['nullable', 'string', 'max:50'],
            'position'      => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $dates = $this->preciseDates($request, [
            'start_date' => true,
            'end_date'   => false,
        ], ['start_date', 'end_date']);

        if ($request->boolean('ongoing')) {
            $dates['end_date'] = null;
        }

        return ['position' => (int) ($data['position'] ?? 0)] + $dates + $data
            + $this->richText($request, 'description', false, 'missions et réalisations');
    }
}
