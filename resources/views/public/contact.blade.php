<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_320px]">
        <div class="card p-6 sm:p-8">
            <x-flash class="mb-6" />

            <form method="POST" action="{{ route('contact.store') }}" x-data="contactForm({ siteKey: @js($recaptchaSiteKey) })" @submit.prevent="submit($event)" class="space-y-5">
                @csrf
                <input type="hidden" name="recaptcha_token" value="">
                {{-- Champ piège anti-robot (masqué aux humains) --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">Ne pas remplir</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="last_name" label="Nom" required autocomplete="family-name" maxlength="100" />
                    <x-form.input name="first_name" label="Prénom" required autocomplete="given-name" maxlength="100" />
                </div>
                <x-form.input name="email" type="email" label="Adresse e-mail" required autocomplete="email" maxlength="255" />
                <x-form.input name="subject" label="Objet" required maxlength="150" />
                <x-form.textarea name="message" label="Message" required rows="7" maxlength="5000" />

                <div>
                    <label class="flex items-start gap-3 text-sm text-slate-600">
                        <input type="checkbox" name="consent" value="1" @checked(old('consent')) required
                               class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        <span>
                            J'accepte que les informations saisies dans ce formulaire soient utilisées pour me recontacter ultérieurement
                            au sujet de ma demande. <span class="text-red-500">*</span>
                        </span>
                    </label>
                    @error('consent')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                @error('recaptcha')<p class="form-error">{{ $message }}</p>@enderror
                <p x-show="captchaError" x-cloak class="form-error">Impossible de joindre le service anti-robot. Vérifiez votre connexion et réessayez.</p>

                <div class="flex flex-col-reverse gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-400">
                        Ce site est protégé par reCAPTCHA : les
                        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" class="underline">règles de confidentialité</a> et les
                        <a href="https://policies.google.com/terms" target="_blank" rel="noopener" class="underline">conditions d'utilisation</a> de Google s'appliquent.
                    </p>
                    <button type="submit" class="btn-primary flex-none px-6 py-3" :disabled="sending">
                        <span x-show="!sending">Envoyer le message</span>
                        <span x-show="sending" x-cloak>Envoi…</span>
                        <x-icon name="arrow-right" class="h-4 w-4" />
                    </button>
                </div>
            </form>
        </div>

        <aside class="space-y-4">
            @if ($profile->email)
                <a href="mailto:{{ $profile->email }}" class="card flex items-center gap-3 p-5 hover:border-primary-200">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600"><x-icon name="mail" /></span>
                    <span class="min-w-0"><span class="block text-xs text-slate-500">E-mail</span><span class="block truncate font-medium text-slate-800">{{ $profile->email }}</span></span>
                </a>
            @endif
            @if ($profile->phone)
                <a href="tel:{{ preg_replace('/[^+0-9]/', '', $profile->phone) }}" class="card flex items-center gap-3 p-5 hover:border-primary-200">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600"><x-icon name="phone" /></span>
                    <span><span class="block text-xs text-slate-500">Téléphone</span><span class="block font-medium text-slate-800">{{ $profile->phone }}</span></span>
                </a>
            @endif
            @if ($profile->location)
                <div class="card flex items-center gap-3 p-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600"><x-icon name="map-pin" /></span>
                    <span><span class="block text-xs text-slate-500">Localisation</span><span class="block font-medium text-slate-800">{{ $profile->location }}</span></span>
                </div>
            @endif
            <p class="px-1 text-xs leading-relaxed text-slate-400">
                Vos données (nom, prénom, e-mail, message) sont uniquement utilisées pour répondre à votre demande et vous recontacter.
                Elles ne sont ni revendues ni transmises à des tiers.
            </p>
        </aside>
    </div>

    @if ($recaptchaSiteKey)
        @push('scripts')
            <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($recaptchaSiteKey) }}" async defer></script>
        @endpush
    @endif
</x-public-layout>
