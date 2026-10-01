<x-guest-layout>
    <h1 class="mb-1 font-display text-xl font-bold text-slate-900">Double authentification</h1>
    <p class="mb-6 text-sm text-slate-500">Confirmez votre identité pour terminer la connexion.</p>

    <div x-data="{ mode: @js($hasKeys ? 'key' : ($errors->has('recovery_code') ? 'recovery' : 'code')) }" class="space-y-5">
        @if ($hasKeys)
            <section x-show="mode === 'key'" x-data="securityKeyLogin({ optionsUrl: @js(route('two-factor.key-options')), verifyUrl: @js(route('two-factor.key')) })"
                     x-init="supported && verify()" class="space-y-3">
                <p class="text-sm text-slate-600">Insérez ou touchez votre clé de sécurité, ou utilisez le capteur de votre appareil.</p>
                <button type="button" @click="verify()" :disabled="busy || ! supported" class="btn-primary w-full">
                    <x-icon name="lock" class="h-4 w-4" /> <span x-text="busy ? 'En attente de la clé…' : 'Utiliser ma clé de sécurité'"></span>
                </button>
                <p x-show="! supported" x-cloak class="form-error">Ce navigateur ne prend pas en charge les clés de sécurité.</p>
                <p x-show="error" x-cloak x-text="error" class="form-error"></p>
            </section>
        @endif

        @if ($hasTotp)
            <form x-show="mode === 'code'" x-cloak method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-3">
                @csrf
                <label for="code" class="form-label">Code de l'application d'authentification</label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code"
                       x-effect="mode === 'code' && $nextTick(() => $el.focus())" class="form-input text-center font-mono text-lg tracking-[0.4em]" placeholder="123456">
                @error('code')<p class="form-error">{{ $message }}</p>@enderror
                <button class="btn-primary w-full">Vérifier</button>
            </form>
        @endif

        <form x-show="mode === 'recovery'" x-cloak method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-3">
            @csrf
            <label for="recovery_code" class="form-label">Code de secours</label>
            <input id="recovery_code" name="recovery_code" type="text" autocomplete="off" class="form-input font-mono" placeholder="xxxxx-xxxxx">
            @error('recovery_code')<p class="form-error">{{ $message }}</p>@enderror
            <p class="form-help">Chaque code de secours ne fonctionne qu'une fois.</p>
            <button class="btn-primary w-full">Vérifier</button>
        </form>

        <div class="space-y-1 border-t border-slate-100 pt-4 text-center text-sm">
            @if ($hasKeys)
                <button type="button" x-show="mode !== 'key'" @click="mode = 'key'" class="block w-full text-primary-600 hover:underline">Utiliser une clé de sécurité</button>
            @endif
            @if ($hasTotp)
                <button type="button" x-show="mode !== 'code'" @click="mode = 'code'" class="block w-full text-primary-600 hover:underline">Utiliser un code d'application</button>
            @endif
            <button type="button" x-show="mode !== 'recovery'" @click="mode = 'recovery'" class="block w-full text-slate-500 hover:underline">Utiliser un code de secours</button>
            <a href="{{ route('login') }}" class="block pt-2 text-slate-400 hover:underline">Annuler</a>
        </div>
    </div>
</x-guest-layout>
