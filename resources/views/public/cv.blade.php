<x-public-layout title="Télécharger mon CV" description="Accès au CV sur code.">
    @push('head')<meta name="robots" content="noindex, nofollow">@endpush
    <div class="mx-auto max-w-md px-4 py-16 sm:px-6">
        <div class="card p-6 sm:p-8">
            <h1 class="font-display text-2xl font-bold text-slate-900">Télécharger mon CV</h1>
            <p class="mt-2 text-sm text-slate-500">Mon CV est accessible sur code, pour le protéger des robots. Saisissez le code qui vous a été communiqué ; pour en obtenir un, <a href="{{ route('contact.show') }}" class="font-medium text-primary-600 underline">contactez-moi</a>.</p>

            <form method="POST" action="{{ route('cv.download.submit') }}" class="mt-6 space-y-5">
                @csrf
                <div class="hidden" aria-hidden="true">
                    <label for="website">Ne pas remplir</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <x-form.input name="code" label="Code d'accès" :value="$code" required maxlength="32" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="ABCD-EFGH" class="font-mono" />
                <button type="submit" class="btn-primary w-full justify-center py-3"><x-icon name="download" class="h-4 w-4" /> Télécharger le CV</button>
            </form>
        </div>
    </div>
</x-public-layout>
