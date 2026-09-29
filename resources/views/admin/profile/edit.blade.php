<x-app-layout>
    <x-slot name="title">Présentation</x-slot>
    <x-slot name="header">Présentation</x-slot>
    <x-slot name="actions">
        <a href="{{ route('home') }}" target="_blank" class="btn-secondary"><x-icon name="eye" class="h-4 w-4" /> Voir</a>
    </x-slot>

    <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <x-admin.section title="Identité" description="Affichée en tête de la page d'accueil et dans le pied de page.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="first_name" label="Prénom" :value="$profile->first_name" />
                <x-form.input name="last_name" label="Nom" :value="$profile->last_name" />
            </div>
            <x-form.input name="headline" label="Titre / accroche" :value="$profile->headline" placeholder="Ex. : Développeur web full-stack" />
            <x-form.image name="photo" label="Photo" :current="$profile->photoUrl()" rounded="rounded-full" help="JPG, PNG, WebP ou GIF — 5 Mo max." />
        </x-admin.section>

        <x-admin.section title="À propos" description="Texte de présentation affiché sur la page d'accueil.">
            <x-form.editor name="about" :value="$profile->about" placeholder="Présentez-vous…" />
        </x-admin.section>

        <x-admin.section title="Coordonnées et liens">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="email" type="email" label="E-mail de contact" :value="$profile->email" help="Reçoit aussi les messages du formulaire de contact (sauf si CONTACT_RECIPIENT est défini)." />
                <x-form.input name="phone" label="Téléphone" :value="$profile->phone" />
                <x-form.input name="location" label="Localisation" :value="$profile->location" placeholder="Ex. : Lyon, France" />
                <x-form.input name="github_url" type="url" label="GitHub" :value="$profile->github_url" placeholder="https://github.com/…" />
                <x-form.input name="linkedin_url" type="url" label="LinkedIn" :value="$profile->linkedin_url" placeholder="https://www.linkedin.com/in/…" />
                <x-form.input name="website_url" type="url" label="Site web" :value="$profile->website_url" />
            </div>
            <div>
                <x-form.input name="cv" type="file" label="CV (PDF)" accept="application/pdf" help="10 Mo max." />
                @if ($profile->cvUrl())
                    <div class="mt-2 flex flex-wrap items-center gap-4 text-sm">
                        <a href="{{ $profile->cvUrl() }}" target="_blank" class="inline-flex items-center gap-1 font-medium text-primary-600"><x-icon name="document" class="h-4 w-4" /> CV actuel</a>
                        <label class="inline-flex items-center gap-2 text-slate-600">
                            <input type="checkbox" name="remove_cv" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500"> Supprimer le CV
                        </label>
                    </div>
                @endif
            </div>
        </x-admin.section>

        <x-admin.form-actions />
    </form>
</x-app-layout>
