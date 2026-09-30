@php $editing = $account->exists; $granted = old('permissions', $account->permissions ?? []); @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le compte de service' : 'Nouveau compte de service' }}</x-slot>
    <x-slot name="header">{{ $editing ? $account->name : 'Nouveau compte de service' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.service-accounts.update', $account) : route('admin.service-accounts.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section>
            <x-form.input name="name" label="Nom" :value="$account->name" required maxlength="100" placeholder="Ex. : Import depuis l'ancien site" />
            <x-form.textarea name="description" label="Usage" :value="$account->description" rows="2" maxlength="500" help="À quoi sert ce compte ? Qui l'utilise ?" />
            <x-form.checkbox name="is_active" label="Compte actif" :checked="$account->is_active" help="Un compte désactivé ne peut plus utiliser l'API, même avec des codes valides." />
        </x-admin.section>

        <x-admin.section title="Autorisations" description="Accordez uniquement ce dont l'outil a besoin. Toute requête hors de ces autorisations est refusée.">
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

        <x-admin.form-actions :cancel="$editing ? route('admin.service-accounts.show', $account) : route('admin.service-accounts.index')" />
    </form>

    @if ($editing)
        <form method="POST" action="{{ route('admin.service-accounts.destroy', $account) }}" onsubmit="return confirm('Supprimer ce compte de service et invalider tous ses codes ?')" class="text-right">
            @csrf @method('DELETE')
            <button class="btn-ghost text-red-600"><x-icon name="trash" class="h-4 w-4" /> Supprimer ce compte</button>
        </form>
    @endif
</x-app-layout>
