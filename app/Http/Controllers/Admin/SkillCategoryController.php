<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SkillCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (SkillCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->exists()) {
            return back()->withErrors(['name' => 'Cette catégorie existe déjà.'], 'category')->withInput();
        }

        SkillCategory::create(['position' => $data['position'] ?? (int) SkillCategory::max('position') + 1] + $data);

        return back()->with('success', 'Catégorie ajoutée.');
    }

    /**
     * Renomme la catégorie (ses compétences suivent). Si l'intitulé est celui
     * d'une autre catégorie, les deux sont fusionnées.
     */
    public function update(Request $request, SkillCategory $category): RedirectResponse
    {
        $data = $this->validated($request);

        $target = SkillCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->whereKeyNot($category->id)->first();

        DB::transaction(function () use ($category, $data, $target) {
            $name = $target?->name ?? $data['name'];
            Skill::where('category', $category->name)->get()->each->update(['category' => $name]);

            if ($target) {
                $category->delete();
            } else {
                $category->update(['name' => $name, 'position' => $data['position'] ?? $category->position]);
            }
        });

        return back()->with('success', $target ? "Catégorie fusionnée dans « {$target->name} »." : 'Catégorie mise à jour.');
    }

    /** Supprime la catégorie ; ses compétences sont conservées, sans catégorie. */
    public function destroy(SkillCategory $category): RedirectResponse
    {
        DB::transaction(function () use ($category) {
            Skill::where('category', $category->name)->get()->each->update(['category' => null]);
            $category->delete();
        });

        return back()->with('success', 'Catégorie supprimée.');
    }

    /** @return array{name: string, position?: int} */
    protected function validated(Request $request): array
    {
        $data = $request->validateWithBag('category', [
            'name'     => ['required', 'string', 'max:100'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $data['name'] = trim($data['name']);
        if (! isset($data['position'])) {
            unset($data['position']);
        }

        return $data;
    }
}
