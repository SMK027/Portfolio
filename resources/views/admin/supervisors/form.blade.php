@php $editing = $supervisor->exists; $granted = old('permissions', $supervisor->permissions ?? []); @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le superviseur' : 'Nouveau superviseur' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Superviseur '.$supervisor->username : 'Nouveau superviseur' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.supervisors.update', $supervisor) : route('admin.supervisors.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="username" label="Identifiant" :value="$supervisor->username" required maxlength="50" autocomplete="off" help="Lettres, chiffres, tirets." />
                <x-form.autocomplete name="user_id" label="Administrateur rattaché" :options="$admins" :selected="array_filter([$supervisor->user_id])" required
                                     placeholder="Nom, identifiant ou e-mail…"
                                     :help="$editing ? 'Le réattribuer à un autre administrateur impose un nouveau code PIN.' : 'Administrateurs et super-administrateurs.'" />
                <x-form.input name="pin" type="password" :label="$editing ? 'Nouveau code PIN (laisser vide pour conserver)' : 'Code PIN'" :required="! $editing"
                              inputmode="numeric" pattern="[0-9]{4,8}" minlength="4" maxlength="8" autocomplete="new-password" help="4 à 8 chiffres, stocké sous forme d'empreinte." />
                <x-form.input name="pin_confirmation" type="password" label="Confirmation du PIN" :required="! $editing" inputmode="numeric" maxlength="8" autocomplete="new-password" />
            </div>
            <x-form.checkbox name="is_active" label="Superviseur actif" :checked="$supervisor->is_active ?? true" help="Décoché : désactivation provisoire, ses validations sont refusées." />
        </x-admin.section>

        <x-admin.section title="Opérations qu'il peut valider">
            <div class="grid gap-6 md:grid-cols-2">
                @foreach (\App\Support\ServicePermissions::GROUPS as $group => $permissions)
                    <fieldset>
                        <legend class="form-label">{{ $group }}</legend>
                        <div class="space-y-1.5">
                            @foreach ($permissions as $key => $label)
                                <label class="flex items-start gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $granted, true)) class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                    <span>{{ $label }} <code class="text-xs text-slate-400">{{ $key }}</code></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
            @error('permissions')<p class="form-error">{{ $message }}</p>@enderror
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.supervisors.index')" />
    </form>
</x-app-layout>
