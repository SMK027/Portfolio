<x-public-layout :page="$page" title="Vérification de votre adresse e-mail">
    <div class="mx-auto max-w-xl px-4 py-12 sm:px-6">
        <div class="card p-6 sm:p-8">
            <x-flash class="mb-6" />

            <h1 class="font-display text-2xl font-semibold text-slate-900">Vérifiez votre adresse e-mail</h1>
            <p class="mt-3 text-sm text-slate-600">
                Un code à 6 chiffres a été envoyé à <strong class="text-slate-800">{{ $email }}</strong>.
                Saisissez-le ci-dessous pour envoyer votre message. Il est valable {{ $minutes }} minutes.
            </p>

            <form method="POST" action="{{ route('contact.verify') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="code" class="form-label">Code de vérification</label>
                    <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7"
                           required autofocus class="form-input mt-1 w-full text-center font-mono text-2xl tracking-[0.5em]" placeholder="000000">
                    @error('code')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary w-full justify-center py-3">
                    Confirmer et envoyer le message <x-icon name="arrow-right" class="h-4 w-4" />
                </button>
            </form>

            <div class="mt-6 border-t border-slate-100 pt-4 text-sm text-slate-600">
                @if ($blockedUntil)
                    <p class="text-amber-800">
                        Limite de {{ \App\Models\ContactVerification::MAX_SENDS }} envois de code atteinte : nouvel envoi possible à partir du
                        {{ $blockedUntil->timezone(config('app.timezone'))->translatedFormat('j F Y à H:i') }}.
                    </p>
                @else
                    <form method="POST" action="{{ route('contact.resend') }}" class="flex flex-wrap items-center justify-between gap-2">
                        @csrf
                        <span>Code non reçu ? Pensez à vérifier vos courriers indésirables.</span>
                        <button type="submit" class="font-medium text-primary-600 hover:underline">
                            Renvoyer un code ({{ $remaining }} envoi{{ $remaining > 1 ? 's' : '' }} restant{{ $remaining > 1 ? 's' : '' }})
                        </button>
                    </form>
                @endif
                <a href="{{ route('contact.show') }}" class="mt-3 inline-block text-slate-500 hover:underline">Revenir au formulaire</a>
            </div>

            @if ($supervisable)
                <details class="mt-6 border-t border-slate-100 pt-4 text-sm" @if ($errors->has('username') || $errors->has('pin')) open @endif>
                    <summary class="cursor-pointer text-slate-500 hover:text-slate-700">Validation superviseur</summary>
                    <form method="POST" action="{{ route('contact.supervise') }}" class="mt-4 space-y-4">
                        @csrf
                        <p class="text-xs text-slate-500">Un superviseur habilité peut valider l'envoi du message sans le code reçu par e-mail.</p>
                        <x-form.input name="username" label="Identifiant superviseur" required maxlength="50" autocomplete="off" />
                        <x-form.input name="pin" type="password" label="Code PIN" required inputmode="numeric" maxlength="8" autocomplete="off" />
                        <button type="submit" class="btn-secondary w-full justify-center">Valider l'envoi</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</x-public-layout>
