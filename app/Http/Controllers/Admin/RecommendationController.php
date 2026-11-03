<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recommendation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecommendationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('statut');

        return view('admin.recommendations.index', [
            'status'          => array_key_exists((string) $status, Recommendation::STATUSES) ? $status : null,
            'pendingCount'    => Recommendation::where('status', Recommendation::PENDING)->count(),
            'recommendations' => Recommendation::query()
                ->when(array_key_exists((string) $status, Recommendation::STATUSES), fn ($q) => $q->where('status', $status))
                ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', [Recommendation::PENDING, Recommendation::APPROVED, Recommendation::INVITED])
                ->ordered()
                ->get(),
        ]);
    }

    /** Nouvelle invitation : un lien personnel à transmettre à l'auteur. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label'            => ['required', 'string', 'max:150'],
            'token_expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $recommendation = Recommendation::invite($data['label'], isset($data['token_expires_at']) ? \Illuminate\Support\Carbon::parse($data['token_expires_at'])->endOfDay() : null);

        return redirect()->route('admin.recommendations.index')
            ->with('success', "Invitation créée pour « {$recommendation->label} » : copiez le lien et transmettez-le.");
    }

    public function edit(Recommendation $recommendation): View
    {
        return view('admin.recommendations.form', ['recommendation' => $recommendation]);
    }

    public function update(Request $request, Recommendation $recommendation): RedirectResponse
    {
        $data = $request->validate([
            'label'          => ['required', 'string', 'max:150'],
            'author_name'    => ['nullable', 'required_if:status,'.Recommendation::APPROVED, 'string', 'max:120'],
            'author_role'    => ['nullable', 'string', 'max:150'],
            'author_company' => ['nullable', 'string', 'max:150'],
            'relationship'   => ['nullable', 'string', 'max:150'],
            'author_url'     => ['nullable', 'url:http,https', 'max:2048'],
            'content'        => ['nullable', 'required_if:status,'.Recommendation::APPROVED, 'string', 'max:3000'],
            'status'         => ['required', Rule::in(array_keys(Recommendation::STATUSES))],
            'is_featured'    => ['nullable', 'boolean'],
            'position'       => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'author_name.required_if' => 'Une recommandation publiée doit avoir un auteur.',
            'content.required_if'     => 'Une recommandation publiée doit avoir un texte.',
        ]);

        $recommendation->fill([
            ...$data,
            'is_featured' => $request->boolean('is_featured'),
            'position'    => (int) ($data['position'] ?? 0),
        ]);
        if ($recommendation->isDirty('status')) {
            $recommendation->approved_at = $recommendation->status === Recommendation::APPROVED ? now() : null;
        }
        $recommendation->save();

        return redirect()->route('admin.recommendations.index')->with('success', 'Recommandation mise à jour.');
    }

    public function destroy(Recommendation $recommendation): RedirectResponse
    {
        $recommendation->delete();

        return back()->with('success', 'Recommandation supprimée.');
    }
}
