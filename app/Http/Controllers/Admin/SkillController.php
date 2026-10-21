<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SkillController extends Controller
{
    public function index(): View
    {
        return view('admin.skills.index', [
            'groups'     => Skill::groupByCategory(Skill::ordered()->withCount('projects')->get()),
            'categories' => SkillCategory::ordered()->withCount('skills')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.skills.form', [
            'skill'      => new Skill(['position' => 0]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Skill::create($this->validated($request));

        return redirect()->route('admin.competences.index')->with('success', 'Compétence ajoutée.');
    }

    public function edit(Skill $skill): View
    {
        return view('admin.skills.form', [
            'skill'      => $skill,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $skill->update($this->validated($request));

        return redirect()->route('admin.competences.index')->with('success', 'Compétence mise à jour.');
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        $skill->delete();

        return back()->with('success', 'Compétence supprimée.');
    }

    /** @return list<string> */
    protected function categories(): array
    {
        return SkillCategory::ordered()->pluck('name')->all();
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'category'    => ['nullable', 'string', 'max:100'],
            'level'       => ['nullable', 'integer', 'min:1', 'max:'.Skill::MAX_LEVEL],
            'description' => ['nullable', 'string', 'max:500'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return ['position' => (int) ($data['position'] ?? 0)] + $data;
    }
}
