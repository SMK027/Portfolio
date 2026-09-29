@php $editing = $announcement->exists; @endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier l\'annonce' : 'Nouvelle annonce' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier l\'annonce' : 'Nouvelle annonce' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.annonces.update', $announcement) : route('admin.annonces.store') }}" class="space-y-6"
          x-data="{ title: @js(old('title', $announcement->title) ?? ''), message: @js(old('message', $announcement->message) ?? ''), style: @js(old('style', $announcement->style)), label: @js(old('link_label', $announcement->link_label) ?? ''), url: @js(old('link_url', $announcement->link_url) ?? '') }">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Aperçu en direct --}}
        <div class="overflow-hidden rounded-xl shadow-sm">
            <div class="bg-gradient-to-r px-4 py-3 text-sm"
                 :class="{
                    'from-primary-600 to-accent-600 text-white': style === 'primary',
                    'from-emerald-600 to-teal-600 text-white': style === 'success',
                    'from-sky-600 to-blue-600 text-white': style === 'info',
                    'from-amber-400 to-orange-400 text-amber-950': style === 'warning',
                 }">
                <p class="text-xs uppercase tracking-wide opacity-75">Aperçu</p>
                <p><strong x-text="title || 'Titre de l\'annonce'"></strong> <span class="opacity-90" x-text="message"></span>
                    <span x-show="url" class="ml-2 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold" x-text="(label || 'En savoir plus') + ' →'"></span></p>
            </div>
        </div>

        <x-admin.section title="Contenu">
            <x-form.input name="title" label="Titre" :value="$announcement->title" required maxlength="150" x-model="title" placeholder="Ex. : Je recherche une alternance pour septembre 2027" />
            <x-form.textarea name="message" label="Message" :value="$announcement->message" rows="2" maxlength="500" x-model="message" placeholder="Ex. : BTS SIO SLAM — rythme 2 semaines / 2 semaines, région lyonnaise." />
            <fieldset>
                <legend class="form-label">Style</legend>
                <div class="grid gap-2 sm:grid-cols-4">
                    @foreach (\App\Models\Announcement::STYLES as $value => [$label, $icon])
                        <label class="cursor-pointer">
                            <input type="radio" name="style" value="{{ $value }}" x-model="style" class="peer sr-only">
                            <span class="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:text-primary-700 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500">
                                <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('style')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
        </x-admin.section>

        <x-admin.section title="Lien (facultatif)" description="Par exemple vers la page Contact, votre CV ou une offre.">
            <div class="grid gap-5 sm:grid-cols-[1fr_220px]">
                <x-form.input name="link_url" type="url" label="URL" :value="$announcement->link_url" x-model="url" placeholder="{{ url('/contact') }}" />
                <x-form.input name="link_label" label="Texte du bouton" :value="$announcement->link_label" maxlength="60" x-model="label" placeholder="En savoir plus" />
            </div>
        </x-admin.section>

        <x-admin.section title="Diffusion">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.input name="starts_at" type="datetime-local" label="Début" :value="$announcement->starts_at?->format('Y-m-d\TH:i')" help="Vide = immédiatement." />
                <x-form.input name="ends_at" type="datetime-local" label="Fin" :value="$announcement->ends_at?->format('Y-m-d\TH:i')" help="Vide = sans fin." />
                <x-form.input name="position" type="number" label="Ordre" :value="$announcement->position" min="0" max="999" />
            </div>
            <x-form.checkbox name="is_active" label="Annonce active" :checked="$announcement->is_active" />
            <x-form.checkbox name="is_dismissible" label="Le visiteur peut masquer l'annonce" :checked="$announcement->is_dismissible"
                             help="Une annonce masquée réapparaît si vous la modifiez." />
        </x-admin.section>

        <x-admin.form-actions :cancel="route('admin.annonces.index')" />
    </form>
</x-app-layout>
