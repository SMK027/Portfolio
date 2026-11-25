<?php

namespace App\Http\Controllers\Admin;

use App\Achievements\Triggers;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AchievementSettings;
use App\Models\AchievementUnlock;
use App\Models\AchievementVisitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Succès : réglages, liste avec statistiques de déblocage, et gestion des succès. */
class AchievementController extends Controller
{
    public function index(): View
    {
        $visitors = AchievementVisitor::count();

        return view('admin.achievements.index', [
            'achievements' => Achievement::ordered()->withCount('unlocks')->get(),
            'settings'     => AchievementSettings::current(),
            'visitors'     => $visitors,
            'unlocks'      => AchievementUnlock::count(),
            'lastWeek'     => AchievementUnlock::where('unlocked_at', '>=', now()->subWeek())->count(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'title'   => ['required', 'string', 'max:100'],
            'intro'   => ['nullable', 'string', 'max:500'],
        ], [], ['title' => 'titre', 'intro' => 'introduction']);

        AchievementSettings::save([...$data, 'enabled' => $request->boolean('enabled'), 'intro' => (string) ($data['intro'] ?? '')]);

        return back()->with('success', 'Réglages des succès enregistrés.');
    }

    /** Efface toutes les progressions (les succès eux-mêmes sont conservés). */
    public function resetProgress(): RedirectResponse
    {
        AchievementVisitor::query()->delete();

        return back()->with('success', 'Progression des visiteurs effacée.');
    }

    public function create(): View
    {
        return view('admin.achievements.form', ['achievement' => new Achievement([
            'icon' => 'trophy', 'category' => 'exploration', 'trigger' => 'sections_visited',
            'threshold' => 1, 'is_active' => true, 'position' => Achievement::max('position') + 1,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Achievement::create($this->validated($request));

        return redirect()->route('admin.succes.index')->with('success', 'Succès ajouté.');
    }

    public function edit(Achievement $achievement): View
    {
        return view('admin.achievements.form', ['achievement' => $achievement->loadCount('unlocks')]);
    }

    public function update(Request $request, Achievement $achievement): RedirectResponse
    {
        $achievement->update($this->validated($request));

        return redirect()->route('admin.succes.index')->with('success', 'Succès mis à jour.');
    }

    public function destroy(Achievement $achievement): RedirectResponse
    {
        $achievement->delete();

        return back()->with('success', 'Succès supprimé.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon'        => ['required', Rule::in(Achievement::ICONS)],
            'category'    => ['required', Rule::in(array_keys(Triggers::CATEGORIES))],
            'trigger'     => ['required', Rule::in(array_keys(Triggers::ALL))],
            'threshold'   => ['required', 'integer', 'min:1', 'max:100000'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_secret'   => ['boolean'],
            'is_active'   => ['boolean'],
        ], [], ['title' => 'titre', 'icon' => 'icône', 'category' => 'catégorie', 'trigger' => 'déclencheur', 'threshold' => 'seuil']);

        return [
            ...$data,
            'position'  => (int) ($data['position'] ?? 0),
            'is_secret' => $request->boolean('is_secret'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
