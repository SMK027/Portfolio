@php
    $editing = $contact->exists;
    $methods = \App\Models\InternshipContact::METHODS;
    // Doublons signalés lors d'un ajout refusé : affichés dès le rechargement.
    $initialDuplicates = session('duplicates')
        ? \App\Models\InternshipContact::similarTo(old('company'), old('address'))
            ->map(fn ($d) => \App\Http\Controllers\Admin\InternshipSearchController::presentDuplicate($d['contact'], $d['reasons']))->all()
        : [];
@endphp
<x-app-layout>
    <x-slot name="title">{{ $editing ? 'Modifier le contact' : 'Nouveau contact' }}</x-slot>
    <x-slot name="header">{{ $editing ? 'Modifier le contact' : 'Nouveau contact' }}</x-slot>

    <form method="POST" action="{{ $editing ? route('admin.internship.update', $contact) : route('admin.internship.store') }}" class="space-y-6"
          x-data="{
              status: @js(old('status', $contact->status)),
              duplicates: @js($initialDuplicates),
              timer: null,
              check() {
                  clearTimeout(this.timer);
                  this.timer = setTimeout(async () => {
                      const params = new URLSearchParams({ company: this.$refs.form.company.value, address: this.$refs.form.address.value });
                      @if ($editing) params.set('except', @js($contact->id)); @endif
                      try {
                          const r = await fetch(@js(route('admin.internship.duplicates')) + '?' + params, { headers: { Accept: 'application/json' } });
                          if (r.ok) this.duplicates = (await r.json()).duplicates;
                      } catch (e) {}
                  }, 400);
              },
          }"
          x-ref="form" @input="if (['company', 'address'].includes($event.target.name)) check()">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.section title="Entreprise">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="company" label="Nom de l'entreprise / organisme" :value="$contact->company" required maxlength="150" />
                <x-form.input name="contact_name" label="Interlocuteur" :value="$contact->contact_name" maxlength="150" help="Facultatif." />
            </div>
            <x-form.input name="address" label="Adresse / code postal" :value="$contact->address" required maxlength="255" />

            {{-- Entreprises semblables déjà suivies (vérification en direct) --}}
            <div x-show="duplicates.length" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status" aria-live="polite">
                <p class="flex items-center gap-1.5 font-medium"><x-icon name="users" class="h-4 w-4" /> Entrée semblable déjà enregistrée</p>
                <ul class="mt-2 space-y-1.5">
                    <template x-for="d in duplicates" :key="d.id">
                        <li class="flex flex-wrap items-baseline gap-x-2">
                            <a :href="d.url" target="_blank" class="font-medium underline" x-text="d.company"></a>
                            <span class="text-xs text-amber-800" x-text="[d.contact_name, d.address, 'contacté le ' + d.contacted_on, d.status].filter(Boolean).join(' · ')"></span>
                            <span class="rounded bg-amber-100 px-1.5 text-xs" x-text="d.reasons.join(', ')"></span>
                        </li>
                    </template>
                </ul>
                @unless ($editing)
                    <label class="mt-3 flex items-center gap-2">
                        <input type="checkbox" name="confirm_duplicate" value="1" class="rounded border-amber-300 text-amber-600" @checked(old('confirm_duplicate'))>
                        Il s'agit bien d'un nouveau contact : l'ajouter quand même
                    </label>
                @endunless
            </div>
        </x-admin.section>

        <x-admin.section title="Démarche">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.input name="contacted_on" type="date" label="Date de contact" :value="$contact->contacted_on?->toDateString()" required />
                <x-form.select name="contact_method" label="Moyen de contact" :options="$methods" :value="$contact->contact_method" placeholder="—" required />
                <x-form.select name="status" label="Résultat" :options="\App\Models\InternshipContact::STATUSES" :value="$contact->status" required x-model="status" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="followed_up_on" type="date" label="Relance" :value="$contact->followed_up_on?->toDateString()" help="Date de la dernière relance effectuée, conservée quel que soit le résultat." />
                <div x-show="['pending', 'processing'].includes(status)" x-cloak>
                    <x-form.input name="follow_up_on" type="date" label="Relance prévue" :value="$contact->follow_up_on?->toDateString()" help="Facultatif : rappel tant qu'une réponse est attendue." />
                </div>
            </div>
            <x-form.textarea name="notes" label="Notes" :value="$contact->notes" rows="5" maxlength="5000" />
        </x-admin.section>

        <x-admin.form-actions :can="$editing ? 'internship.update' : 'internship.create'" :cancel="route('admin.internship.index')" />
    </form>
</x-app-layout>
