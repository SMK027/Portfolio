<x-public-layout title="Rédiger une recommandation">
    @push('head')<meta name="robots" content="noindex, nofollow">@endpush
    <div class="mx-auto max-w-2xl px-4 py-12 sm:px-6">
        <div class="card p-6 sm:p-8">
            <x-flash class="mb-6" />

            @if (! $open)
                <div class="space-y-2 text-center">
                    <h1 class="font-display text-xl font-bold text-slate-900">Merci !</h1>
                    <p class="text-slate-600">
                        @if ($recommendation->status === \App\Models\Recommendation::APPROVED)
                            Votre recommandation est publiée. Ce lien n'est plus actif.
                        @else
                            Ce lien n'est plus actif. Contactez-moi si vous souhaitez rédiger ou modifier une recommandation.
                        @endif
                    </p>
                </div>
            @else
                <h1 class="font-display text-2xl font-bold text-slate-900">Rédiger une recommandation</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Merci de prendre quelques minutes pour témoigner de notre collaboration. Votre texte sera relu avant d'être publié
                    sur la page « Recommandations », avec votre nom et votre poste. Votre adresse IP n'est pas publiée.
                    @if ($recommendation->status === \App\Models\Recommendation::PENDING)
                        <strong>Vous pouvez modifier votre texte tant qu'il n'est pas publié.</strong>
                    @endif
                    @if ($recommendation->token_expires_at)
                        Lien valable jusqu'au {{ $recommendation->token_expires_at->translatedFormat('j F Y') }}.
                    @endif
                </p>

                <form method="POST" action="{{ route('recommendations.submit', request()->route('token')) }}" class="mt-6 space-y-5">
                    @csrf
                    {{-- Champ piège anti-robot (masqué aux humains) --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Ne pas remplir</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <x-form.input name="author_name" label="Prénom et nom" :value="$recommendation->author_name" required autocomplete="name" maxlength="120" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="author_role" label="Poste" :value="$recommendation->author_role" maxlength="150" placeholder="Ex. : Responsable informatique" autocomplete="organization-title" />
                        <x-form.input name="author_company" label="Entreprise ou établissement" :value="$recommendation->author_company" maxlength="150" autocomplete="organization" />
                    </div>
                    <x-form.input name="relationship" label="Notre relation" :value="$recommendation->relationship" required maxlength="150" placeholder="Ex. : Tuteur de stage (mai – juin 2026)" />
                    <x-form.input name="author_url" type="url" label="Lien vers votre profil (facultatif)" :value="$recommendation->author_url" maxlength="2048" placeholder="https://www.linkedin.com/in/…" />
                    <x-form.textarea name="content" label="Recommandation" :value="$recommendation->content" required rows="8" minlength="30" maxlength="3000" />

                    <div>
                        <label class="flex items-start gap-3 text-sm text-slate-600">
                            <input type="checkbox" name="consent" value="1" @checked(old('consent')) required
                                   class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                            <span>J'accepte que ce texte soit publié sur ce site avec mon nom, mon poste et le lien indiqué. Je peux demander son retrait à tout moment. <span class="text-red-500">*</span></span>
                        </label>
                        @error('consent')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary px-6 py-3">{{ $recommendation->status === \App\Models\Recommendation::PENDING ? 'Mettre à jour' : 'Envoyer' }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-public-layout>
