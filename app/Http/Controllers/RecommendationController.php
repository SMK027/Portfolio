<?php

namespace App\Http\Controllers;

use App\Models\Recommendation;
use App\Services\DiscordNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecommendationController extends Controller
{
    public function index(Request $request): View
    {
        return view('public.recommendations', [
            'page'            => $request->attributes->get('page'),
            'recommendations' => Recommendation::published()->ordered()->get(),
        ]);
    }

    /** Formulaire de l'auteur invité (lien personnel, indépendant de la visibilité de la page). */
    public function invite(string $token): View
    {
        $recommendation = Recommendation::where('token', $token)->firstOrFail();

        return view('public.recommendation-invite', [
            'recommendation' => $recommendation,
            'open'           => $recommendation->invitationIsOpen(),
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $recommendation = Recommendation::findByToken($token) ?? abort(404);

        $data = $request->validate([
            'author_name'    => ['required', 'string', 'max:120'],
            'author_role'    => ['nullable', 'string', 'max:150'],
            'author_company' => ['nullable', 'string', 'max:150'],
            'relationship'   => ['required', 'string', 'max:150'],
            'author_url'     => ['nullable', 'url:http,https', 'max:2048'],
            'content'        => ['required', 'string', 'min:30', 'max:3000'],
            'consent'        => ['accepted'],
            'website'        => ['prohibited'], // champ piège anti-robot
        ], [], [
            'author_name' => 'nom', 'author_role' => 'poste', 'author_company' => 'entreprise ou établissement',
            'relationship' => 'relation', 'author_url' => 'lien', 'content' => 'recommandation', 'consent' => 'consentement',
        ]);

        $updating = $recommendation->status === Recommendation::PENDING;
        $recommendation->fill(collect($data)->except(['consent', 'website'])->all());
        $recommendation->forceFill([
            'status'       => Recommendation::PENDING,
            'ip_address'   => $request->ip(),
            'consented_at' => now(),
            'submitted_at' => now(),
        ])->save();

        app(DiscordNotifier::class)->notify('recommendation',
            ($updating ? 'Recommandation modifiée : ' : 'Nouvelle recommandation : ').$recommendation->author_name,
            \Illuminate\Support\Str::limit($recommendation->content, 500),
            ['Relation' => $recommendation->relationship, 'Poste' => $recommendation->authorTitle()],
            route('admin.recommendations.edit', $recommendation),
        );

        return redirect()->route('recommendations.invite', $token)
            ->with('success', 'Merci ! Votre recommandation a bien été envoyée. Elle sera publiée après relecture.');
    }
}
