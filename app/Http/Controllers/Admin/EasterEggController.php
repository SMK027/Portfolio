<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityRule;
use App\Models\EasterEgg;
use App\Models\EasterEggSettings;
use App\Services\EasterEggs;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Page d'easter eggs : réglages d'accès et éléments. */
class EasterEggController extends Controller
{
    use HandlesUploads;

    public function index(): View
    {
        return view('admin.easter-eggs.index', ['eggs' => EasterEgg::ordered()->get(), 'settings' => EasterEggSettings::current()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled'        => ['boolean'],
            'title'          => ['required', 'string', 'max:150'],
            'intro'          => ['nullable', 'string', 'max:2000'],
            'access_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'sequence'       => ['nullable', 'string', 'max:255'],
            'word'           => ['nullable', 'string', 'max:40', 'regex:/^\S+$/u'],
            'click_selector' => ['nullable', 'string', 'max:150'],
            'click_count'    => ['required', 'integer', 'min:2', 'max:30'],
        ], ['word.regex' => 'Le mot magique ne doit pas contenir d\'espace.'], [
            'title' => 'titre', 'access_minutes' => 'durée d\'accès', 'sequence' => 'séquence de touches',
            'word' => 'mot magique', 'click_selector' => 'élément à cliquer', 'click_count' => 'nombre de clics',
        ]);

        EasterEggSettings::save([
            ...$data,
            'enabled'        => $request->boolean('enabled'),
            'intro'          => (string) ($data['intro'] ?? ''),
            'sequence'       => trim((string) ($data['sequence'] ?? '')),
            'word'           => trim((string) ($data['word'] ?? '')),
            'click_selector' => trim((string) ($data['click_selector'] ?? '')),
            'access_minutes' => (int) $data['access_minutes'],
            'click_count'    => (int) $data['click_count'],
        ]);

        return redirect()->route('admin.easter-eggs.index')->with('success', 'Réglages enregistrés.');
    }

    public function create(): View
    {
        return view('admin.easter-eggs.form', ['egg' => new EasterEgg(['type' => 'text', 'is_active' => true, 'position' => 0, 'country_mode' => 'only', 'min_unlocked' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $egg = new EasterEgg;
        $egg->fill($this->validated($request, $egg))->save();

        return redirect()->route('admin.easter-eggs.index')->with('success', 'Élément ajouté.');
    }

    public function edit(EasterEgg $easterEgg): View
    {
        return view('admin.easter-eggs.form', ['egg' => $easterEgg]);
    }

    public function update(Request $request, EasterEgg $easterEgg): RedirectResponse
    {
        $easterEgg->update($this->validated($request, $easterEgg));

        return redirect()->route('admin.easter-eggs.index')->with('success', 'Élément mis à jour.');
    }

    public function destroy(EasterEgg $easterEgg): RedirectResponse
    {
        if ($easterEgg->image_path) {
            Storage::disk('local')->delete($easterEgg->image_path);
        }
        $easterEgg->delete();

        return back()->with('success', 'Élément supprimé.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, EasterEgg $egg): array
    {
        $data = $request->validate([
            'title'           => ['required', 'string', 'max:150'],
            'type'            => ['required', Rule::in(array_keys(EasterEgg::TYPES))],
            'body'            => ['nullable', 'string', 'max:10000', Rule::requiredIf($request->input('type') === 'text')],
            'image'           => [...$this->imageRules(10240), Rule::requiredIf($request->input('type') === 'image' && ! $egg->image_path)],
            'link_url'        => ['nullable', 'url:http,https', 'max:2048', Rule::requiredIf($request->input('type') === 'link')],
            'link_label'      => ['nullable', 'string', 'max:60'],
            'effect'          => ['nullable', Rule::in(array_keys(EasterEgg::EFFECTS)), Rule::requiredIf($request->input('type') === 'effect')],
            'unlock_sequence' => ['nullable', 'string', 'max:255'],
            'hint'            => ['nullable', 'string', 'max:255'],
            'starts_at'       => ['nullable', 'date'],
            'ends_at'         => ['nullable', 'date', 'after:starts_at'],
            'weekdays'        => ['nullable', 'array'],
            'weekdays.*'      => ['integer', Rule::in(array_keys(AvailabilityRule::WEEKDAYS))],
            'time_from'       => ['nullable', 'date_format:H:i', 'required_with:time_to'],
            'time_to'         => ['nullable', 'date_format:H:i', 'required_with:time_from', 'different:time_from'],
            'device'          => ['nullable', Rule::in(array_keys(EasterEgg::DEVICES))],
            'countries'       => ['nullable', 'string', 'max:300', 'regex:/^\s*([A-Za-z]{2}[\s,;]*)*$/'],
            'country_mode'    => ['required', 'in:only,except'],
            'min_unlocked'    => ['nullable', 'integer', 'min:0', 'max:999'],
            'position'        => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active'       => ['nullable', 'boolean'],
        ], ['countries.regex' => 'Pays : codes ISO à deux lettres séparés par des virgules (ex. : FR, BE, CH).'], [
            'title' => 'titre', 'body' => 'texte', 'link_url' => 'lien', 'effect' => 'effet', 'unlock_sequence' => 'combinaison',
            'hint' => 'indice', 'time_from' => 'heure de début', 'time_to' => 'heure de fin', 'min_unlocked' => 'progression',
        ]);

        $image = $egg->image_path;
        if ($request->hasFile('image')) {
            $image && Storage::disk('local')->delete($image);
            // Disque privé : l'image n'est servie qu'aux visiteurs qui ont trouvé l'élément.
            $image = app(ImageOptimizer::class)->store($request->file('image'), 'easter-eggs', 'local')['path'];
        } elseif ($request->boolean('remove_image') && $request->input('type') !== 'image') {
            $image && Storage::disk('local')->delete($image);
            $image = null;
        }
        unset($data['image']);

        // Avec espaces : des touches (« ArrowUp ArrowDown b a ») ; sans : un mot tapé lettre par lettre.
        $raw = trim((string) ($data['unlock_sequence'] ?? ''));
        $sequence = implode(' ', EasterEggs::keys($raw, ! str_contains($raw, ' ')));

        return [
            ...$data,
            'image_path'      => $image,
            'unlock_sequence' => $sequence ?: null,
            'weekdays'        => ($data['weekdays'] ?? []) && count($data['weekdays']) < 7 ? array_map('intval', $data['weekdays']) : null,
            'countries'       => collect(preg_split('/[\s,;]+/', strtoupper((string) ($data['countries'] ?? ''))))->filter()->unique()->values()->all() ?: null,
            'min_unlocked'    => (int) ($data['min_unlocked'] ?? 0),
            'position'        => (int) ($data['position'] ?? 0),
            'is_active'       => $request->boolean('is_active'),
        ];
    }
}
