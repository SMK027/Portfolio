@php
    $user = auth()->user();
    $pendingSecret = session('two_factor.pending_secret') ? \Illuminate\Support\Facades\Crypt::decryptString(session('two_factor.pending_secret')) : null;
    $keys = $user->securityKeys;
    $enabled = $user->hasTotp() || $keys->isNotEmpty();
    $bag = $errors->twoFactor;
@endphp
<section id="double-authentification" class="scroll-mt-24 space-y-6">
    <header>
        <h2 class="flex flex-wrap items-center gap-2 text-lg font-medium text-slate-900">
            Double authentification
            @if ($enabled)<span class="badge-green">Activée</span>@else<span class="badge-slate">Désactivée</span>@endif
        </h2>
        <p class="mt-1 text-sm text-slate-600">
            En plus du mot de passe, la connexion demande une clé de sécurité (YubiKey, Titan, empreinte ou visage de l'appareil…)
            ou un code d'une application (Google Authenticator, Authy, 1Password…). Vous pouvez activer les deux.
        </p>
    </header>

    {{-- Codes de secours affichés une seule fois --}}
    @if (session('recovery_codes'))
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4" x-data="{ copied: false }">
            <p class="text-sm font-semibold text-amber-900">Codes de secours — notez-les maintenant, ils ne seront plus affichés</p>
            <p class="mt-1 text-sm text-amber-900">Chacun permet une connexion si vous perdez votre clé ou votre téléphone, une seule fois.</p>
            <ul x-ref="codes" class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-slate-900">
                @foreach (session('recovery_codes') as $code)<li>{{ $code }}</li>@endforeach
            </ul>
            <button type="button" class="btn-secondary btn-sm mt-3" @click="navigator.clipboard.writeText(@js(implode("\n", session('recovery_codes')))); copied = true">
                <x-icon name="check" class="h-4 w-4" /> <span x-text="copied ? 'Copiés' : 'Copier les codes'"></span>
            </button>
        </div>
    @endif

    {{-- Clés de sécurité --}}
    <div class="space-y-3">
        <h3 class="font-medium text-slate-900">Clés de sécurité</h3>
        @if ($keys->isNotEmpty())
            <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                @foreach ($keys as $key)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-3 py-2">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $key->name }}</p>
                            <p class="text-xs text-slate-500">Ajoutée le {{ $key->created_at->format('d/m/Y') }} · {{ $key->last_used_at ? 'utilisée '.$key->last_used_at->diffForHumans() : 'jamais utilisée' }}</p>
                        </div>
                        <form method="POST" action="{{ route('two-factor.keys.destroy', $key) }}" x-data="{ open: false }" class="flex flex-wrap items-center gap-2">
                            @csrf @method('DELETE')
                            <button type="button" x-show="! open" @click="open = true" class="btn-ghost btn-sm text-red-600">Supprimer</button>
                            <template x-if="open">
                                <span class="flex flex-wrap items-center gap-2">
                                    <input type="password" name="password" required placeholder="Mot de passe" autocomplete="current-password" class="form-input w-40 py-1 text-sm">
                                    <button class="btn-danger btn-sm">Confirmer</button>
                                </span>
                            </template>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <div x-data="securityKeyRegister({ optionsUrl: @js(route('two-factor.keys.options')), storeUrl: @js(route('two-factor.keys.store')) })" class="space-y-2">
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="text" x-model="name" maxlength="100" placeholder="Nom de la clé (ex. : YubiKey bleue)" class="form-input flex-1" @keydown.enter.prevent="register()">
                <button type="button" @click="register()" :disabled="busy || ! name.trim() || ! supported" class="btn-secondary">
                    <x-icon name="plus" class="h-4 w-4" /> <span x-text="busy ? 'Touchez votre clé…' : 'Ajouter une clé'"></span>
                </button>
            </div>
            <p x-show="! supported" x-cloak class="form-error">Ce navigateur ne prend pas en charge les clés de sécurité.</p>
            <p x-show="error" x-cloak x-text="error" class="form-error"></p>
        </div>
    </div>

    {{-- Application d'authentification --}}
    <div class="space-y-3 border-t border-slate-100 pt-5">
        <h3 class="font-medium text-slate-900">Application d'authentification</h3>
        @if ($user->hasTotp())
            <p class="text-sm text-slate-600"><span class="badge-green">Activée</span> le {{ $user->two_factor_confirmed_at->format('d/m/Y') }}.</p>
            <form method="POST" action="{{ route('two-factor.totp.disable') }}" class="flex flex-wrap items-center gap-2">
                @csrf @method('DELETE')
                <input type="password" name="password" required placeholder="Mot de passe actuel" autocomplete="current-password" class="form-input w-56">
                <button class="btn-secondary text-red-600">Désactiver</button>
            </form>
        @elseif ($pendingSecret)
            @php $uri = \App\Support\Totp::uri(config('app.name'), $user->email, $pendingSecret); @endphp
            <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                <li>Scannez ce QR code avec votre application (ou saisissez la clé à la main).</li>
                <li>Saisissez le code à 6 chiffres affiché par l'application.</li>
            </ol>
            <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                <div class="rounded-lg border border-slate-200 bg-white p-2" aria-label="QR code à scanner">{!! \App\Support\Totp::qrSvg($uri) !!}</div>
                <div class="text-sm">
                    <p class="form-label">Clé de configuration</p>
                    <code class="select-all break-all rounded bg-slate-100 px-2 py-1 font-mono text-slate-800">{{ \App\Support\Totp::formatSecret($pendingSecret) }}</code>
                </div>
            </div>
            <form method="POST" action="{{ route('two-factor.totp.confirm') }}" class="flex flex-wrap items-start gap-2">
                @csrf @method('PUT')
                <div>
                    <input type="text" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required autofocus placeholder="123456" class="form-input w-40 text-center font-mono tracking-widest">
                    @if ($bag->has('code'))<p class="form-error">{{ $bag->first('code') }}</p>@endif
                </div>
                <button class="btn-primary">Activer</button>
            </form>
            <form method="POST" action="{{ route('two-factor.totp.cancel') }}">@csrf @method('DELETE')<button class="btn-ghost btn-sm">Annuler</button></form>
        @else
            <form method="POST" action="{{ route('two-factor.totp.start') }}">
                @csrf
                <button class="btn-secondary"><x-icon name="phone" class="h-4 w-4" /> Configurer une application</button>
            </form>
        @endif
    </div>

    {{-- Codes de secours --}}
    @if ($enabled)
        <div class="space-y-3 border-t border-slate-100 pt-5">
            <h3 class="font-medium text-slate-900">Codes de secours</h3>
            <p @class(['text-sm', 'text-amber-700' => $user->recoveryCodesLeft() <= 2, 'text-slate-600' => $user->recoveryCodesLeft() > 2])>
                {{ $user->recoveryCodesLeft() }} code(s) restant(s). Régénérer invalide les anciens.
            </p>
            <form method="POST" action="{{ route('two-factor.recovery-codes') }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="password" name="password" required placeholder="Mot de passe actuel" autocomplete="current-password" class="form-input w-56">
                <button class="btn-secondary">Régénérer les codes</button>
            </form>
        </div>
    @endif

    @if ($bag->has('password'))<p class="form-error">{{ $bag->first('password') }}</p>@endif
</section>
