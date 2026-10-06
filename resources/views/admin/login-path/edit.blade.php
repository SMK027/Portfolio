<x-app-layout>
    <x-slot name="title">Adresse de connexion</x-slot>
    <x-slot name="header">Adresse de connexion</x-slot>

    <form method="POST" action="{{ route('admin.login-path.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-admin.section>
            <div class="flex items-start gap-3">
                <span @class(['flex h-10 w-10 flex-none items-center justify-center rounded-xl', 'bg-emerald-50 text-emerald-600' => $custom, 'bg-slate-100 text-slate-500' => ! $custom])>
                    <x-icon name="lock" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="font-medium text-slate-900">{{ $custom ? 'Adresse personnalisée' : 'Adresse par défaut' }}</p>
                    <p class="break-all text-sm text-slate-500">
                        <a href="{{ route('login') }}" class="font-medium text-primary-600 underline">{{ route('login') }}</a>
                    </p>
                </div>
            </div>

            <div>
                <label for="f-path" class="form-label">Nouvelle adresse</label>
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                    <span class="text-sm text-slate-500">{{ url('/') }}/</span>
                    <input id="f-path" name="path" type="text" value="{{ old('path', $custom ? $path : '') }}"
                           placeholder="ex. acces-prive" autocomplete="off" spellcheck="false" maxlength="64" class="form-input sm:w-80">
                </div>
                <p class="form-help">Lettres minuscules sans accent, chiffres, tirets et « / » (4 à 64 caractères). Videz le champ pour rétablir <code>/login</code>.</p>
                @error('path')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <x-form.input name="current_password" type="password" label="Votre mot de passe actuel" required autocomplete="current-password" class="sm:w-80"
                          help="Exigé pour confirmer le changement." />
        </x-admin.section>

        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
            <p class="font-medium text-slate-800">L'adresse s'applique à toutes les connexions au panel :</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>e-mail et mot de passe, et connexion directe par clé de sécurité ;</li>
                <li>bots, par code d'application (<code>…/bot</code>).</li>
            </ul>
            <p class="mt-3">
                Une fois personnalisée, l'ancienne adresse répond « page introuvable », le lien « Espace administrateur » disparaît
                du site et les pages du panel répondent aussi « page introuvable » aux visiteurs non connectés.
                L'API (codes d'application des comptes de service) n'est pas concernée.
            </p>
            <p class="mt-2">
                Adresse oubliée ? Sur le serveur : <code class="rounded bg-slate-100 px-1">php artisan portfolio:login-path</code> l'affiche,
                <code class="rounded bg-slate-100 px-1">--reset</code> rétablit <code>/login</code>.
            </p>
        </div>

        <x-admin.form-actions />
    </form>
</x-app-layout>
