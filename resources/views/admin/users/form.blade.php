@php $editing = $user->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le compte' : 'Nouveau compte' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier le compte' : 'Nouveau compte' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.utilisateurs.update', $user) : route('admin.utilisateurs.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Nom affiché" :value="$user->name" required />
                <x-form.input name="username" label="Identifiant" :value="$user->username" required help="Lettres, chiffres, tirets et underscores." />
                <x-form.input name="email" type="email" label="Adresse e-mail" :value="$user->email" required />
                <x-form.select name="global_role" label="Rôle" :value="$user->global_role" :options="auth()->user()->isBot() ? \Illuminate\Support\Arr::only(\App\Models\User::ROLES, 'user') : \App\Models\User::ROLES" required />
            </div>
        </x-admin.section>

        <x-admin.section title="Mot de passe" :description="$editing ? 'Laissez vide pour conserver le mot de passe actuel.' : null">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="password" type="password" label="Mot de passe" :required="! $editing" autocomplete="new-password" />
                <x-form.input name="password_confirmation" type="password" label="Confirmation" :required="! $editing" autocomplete="new-password" />
            </div>
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
