@php
    $editing = $user->exists;
    $limited = auth()->user()->hasLimitedAccess();
    $granted = old('permissions', $user->permissions ?? []);
@endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le compte' : 'Nouveau compte' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier le compte' : 'Nouveau compte' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.utilisateurs.update', $user) : route('admin.utilisateurs.store') }}" class="space-y-6"
          x-data="{ role: @js(old('global_role', $user->global_role)) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Nom affiché" :value="$user->name" required />
                <x-form.input name="username" label="Identifiant" :value="$user->username" required help="Lettres, chiffres, tirets et underscores." />
                <x-form.input name="email" type="email" label="Adresse e-mail" :value="$user->email" required />
                <x-form.select name="global_role" label="Rôle" :value="$user->global_role" x-model="role" :options="$limited ? \Illuminate\Support\Arr::only(\App\Models\User::ROLES, 'user') : \App\Models\User::ROLES" required />
            </div>
        </x-admin.section>

        {{-- Personnel : autorisations et désactivation programmée (super-administrateurs) --}}
        @unless ($limited)
            <div x-show="role === 'staff'" x-cloak class="space-y-6">
                <x-admin.section title="Activation" description="Le compte se connecte avec son mot de passe, peut activer la double authentification et, une fois une clé enregistrée, se connecter directement par clé de sécurité.">
                    <x-form.checkbox name="is_active" label="Compte actif" :checked="$user->is_active ?? true" help="Un compte désactivé ne peut plus se connecter ; une session ouverte est coupée immédiatement." />
                    <x-form.input name="deactivates_at" type="datetime-local" label="Désactivation programmée" class="sm:w-72"
                                  :value="$user->deactivates_at?->format('Y-m-d\TH:i')"
                                  :help="'Le compte est désactivé automatiquement à cette date et heure (heure de Paris). Vide : jamais.'.(config('auth.staff_warning_days') > 0 ? ' La personne est prévenue par e-mail '.config('auth.staff_warning_days').' jours avant ; les super-administrateurs le jour même.' : '')" />
                    @if ($user->deactivates_at)
                        <p @class(['rounded-lg px-3 py-2 text-sm', 'bg-red-50 text-red-800' => $user->deactivates_at->isPast(), 'bg-amber-50 text-amber-900' => $user->deactivates_at->isFuture()])>
                            {{ $user->deactivates_at->isPast() ? 'Désactivé depuis le' : 'Sera désactivé le' }}
                            <strong>{{ $user->deactivates_at->translatedFormat('j F Y à H:i') }}</strong> ({{ $user->deactivates_at->diffForHumans() }}).
                        </p>
                    @endif
                </x-admin.section>

                <x-admin.section title="Autorisations" description="Le compte ne voit dans le panel que les sections autorisées. Accordez uniquement le nécessaire.">
                    <div class="grid gap-6 md:grid-cols-2">
                        @foreach (\App\Support\ServicePermissions::GROUPS as $group => $permissions)
                            <fieldset>
                                <legend class="form-label">{{ $group }}</legend>
                                <div class="space-y-1.5">
                                    @foreach ($permissions as $key => $label)
                                        <label class="flex items-start gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $granted, true))
                                                   class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                            <span>{{ $label }} <code class="text-xs text-slate-400">{{ $key }}</code></span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    @error('permissions.*')<p class="form-error">{{ $message }}</p>@enderror
                </x-admin.section>
            </div>
        @endunless

        {{-- Mot de passe et e-mail d'accès : aucun mot de passe n'est envoyé par e-mail --}}
        @php $passwordMode = old('password_mode', $editing ? 'keep' : 'link'); @endphp
        <x-admin.section title="Mot de passe et e-mail" x-data="{ mode: @js($passwordMode) }">
            <fieldset class="space-y-2">
                <legend class="sr-only">Mot de passe</legend>
                @foreach ($editing
                    ? ['keep' => ['Conserver le mot de passe actuel', null],
                       'set'  => ['Définir un nouveau mot de passe', 'À communiquer vous-même à la personne.'],
                       'link' => ['Envoyer un lien de réinitialisation par e-mail', 'La personne choisit elle-même son nouveau mot de passe ; l\'actuel reste valable tant qu\'elle ne l\'a pas changé.']]
                    : ['link' => ['Envoyer une invitation par e-mail', 'La personne reçoit un lien pour choisir elle-même son mot de passe.'],
                       'set'  => ['Définir le mot de passe maintenant', 'À communiquer vous-même à la personne.']] as $value => [$label, $help])
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-primary-400 has-[:checked]:bg-primary-50/50">
                        <input type="radio" name="password_mode" value="{{ $value }}" x-model="mode" @checked($passwordMode === $value) class="mt-0.5 border-slate-300 text-primary-600 focus:ring-primary-500">
                        <span>
                            <span class="block text-sm font-medium text-slate-900">{{ $label }}</span>
                            @if ($help)<span class="block text-xs text-slate-500">{{ $help }}</span>@endif
                        </span>
                    </label>
                @endforeach
                @error('password_mode')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>

            <div x-show="mode === 'set'" x-cloak class="space-y-4">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="password" type="password" label="Mot de passe" autocomplete="new-password" x-bind:required="mode === 'set'" />
                    <x-form.input name="password_confirmation" type="password" label="Confirmation" autocomplete="new-password" x-bind:required="mode === 'set'" />
                </div>
                <x-form.checkbox name="notify" :checked="old('notify', true)"
                                 :label="$editing ? 'Prévenir la personne par e-mail du changement' : 'Envoyer un e-mail de bienvenue (identifiant et lien de connexion)'"
                                 help="Le mot de passe n'apparaît jamais dans l'e-mail." />
            </div>

            <p x-show="mode === 'link'" x-cloak class="text-xs text-slate-500">
                Le lien est envoyé à l'adresse ci-dessus, n'est utilisable qu'une fois et expire après {{ intdiv(config('auth.passwords.account_setup.expire'), 60) }} heures.
            </p>
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.utilisateurs.index')" />
    </form>

    {{-- Perte de la clé / du téléphone et des codes de secours : super-administrateurs --}}
    @if ($editing && auth()->user()->isSuperAdmin() && $user->hasTwoFactor())
        <x-admin.section title="Double authentification" description="Activée sur ce compte. En cas de perte de tous ses facteurs et codes de secours, réinitialisez-la : la personne se connectera avec son seul mot de passe et pourra la reconfigurer.">
            <form method="POST" action="{{ route('admin.utilisateurs.two-factor.reset', $user) }}" onsubmit="return confirm('Réinitialiser la double authentification de ce compte ?')">
                @csrf @method('DELETE')
                <button class="btn-secondary text-red-600"><x-icon name="lock" class="h-4 w-4" /> Réinitialiser la double authentification</button>
            </form>
        </x-admin.section>
    @endif
</x-app-layout>
