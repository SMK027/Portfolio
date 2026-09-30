<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\Hobby;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HobbyController extends Controller
{
    use HandlesUploads;

    public function index(): View
    {
        return view('admin.hobbies.index', ['hobbies' => Hobby::ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.hobbies.form', ['hobby' => new Hobby(['position' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hobby = new Hobby;
        $hobby->fill($this->validated($request, $hobby))->save();

        return redirect()->route('admin.loisirs.index')->with('success', 'Loisir ajouté.');
    }

    public function edit(Hobby $hobby): View
    {
        return view('admin.hobbies.form', ['hobby' => $hobby]);
    }

    public function update(Request $request, Hobby $hobby): RedirectResponse
    {
        $hobby->update($this->validated($request, $hobby));

        return redirect()->route('admin.loisirs.index')->with('success', 'Loisir mis à jour.');
    }

    public function destroy(Hobby $hobby): RedirectResponse
    {
        $this->deletePublicFile($hobby->image_path);
        $hobby->delete();

        return back()->with('success', 'Loisir supprimé.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, Hobby $hobby): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:999'],
            'image'       => $this->imageRules(),
        ]);

        $data['image_path'] = $this->syncPublicFile($request, 'image', $hobby->image_path, 'hobbies');
        $data['position'] = (int) ($data['position'] ?? 0);
        unset($data['image']);

        return $data;
    }
}
