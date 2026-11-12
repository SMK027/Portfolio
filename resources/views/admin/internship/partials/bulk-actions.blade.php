{{-- Action groupée sur les contacts cochés de la page (cases liées au formulaire par form="bulk-form"). --}}
<div class="flex flex-wrap items-center gap-2 text-xs">
    <button type="button" class="btn-ghost btn-sm"
            @click="selected = selected.length === page.length ? [] : [...page]"
            x-text="selected.length === page.length ? 'Tout désélectionner' : 'Tout sélectionner sur la page'">Tout sélectionner sur la page</button>
    <span class="text-slate-500" x-show="selected.length" x-cloak x-text="selected.length + ' sélectionné(s)'"></span>
</div>

@if ($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
        <p class="font-semibold">Action groupée non appliquée :</p>
        <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form id="bulk-form" method="POST" action="{{ route('admin.internship.bulk') }}" x-show="selected.length" x-cloak
      class="card sticky top-2 z-10 space-y-3 border-primary-200 bg-primary-50/60 p-4 shadow-md"
      @submit="if ($event.submitter?.value === 'delete' && ! confirm('Supprimer définitivement les ' + selected.length + ' contact(s) sélectionné(s) ?')) $event.preventDefault()">
    @csrf
    <p class="text-sm font-semibold text-slate-800"><span x-text="selected.length"></span> contact(s) sélectionné(s) — champs laissés vides inchangés</p>

    @if ($canUpdate)
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-xs font-medium text-slate-600">Résultat
                <select name="status" class="form-input mt-1">
                    <option value="">— Inchangé —</option>
                    @foreach (\App\Models\InternshipContact::STATUSES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Moyen de contact
                <select name="contact_method" class="form-input mt-1">
                    <option value="">— Inchangé —</option>
                    @foreach (\App\Models\InternshipContact::METHODS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            @foreach (['followed_up_on' => 'Relance', 'follow_up_on' => 'Relance prévue'] as $field => $label)
                <div class="text-xs font-medium text-slate-600" x-data="{ clear: false }">
                    <label for="bulk-{{ $field }}">{{ $label }}</label>
                    <input id="bulk-{{ $field }}" type="date" name="{{ $field }}" class="form-input mt-1" :disabled="clear">
                    <label class="mt-1 flex items-center gap-1.5 font-normal text-slate-500">
                        <input type="checkbox" name="clear_{{ $field }}" value="1" x-model="clear" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"> Effacer
                    </label>
                </div>
            @endforeach
        </div>
    @endif

    <div class="flex flex-wrap justify-end gap-2">
        <button type="button" class="btn-ghost btn-sm" @click="selected = []">Annuler</button>
        @if ($canDelete)
            <button type="submit" name="action" value="delete" class="btn-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium text-red-600 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Supprimer</button>
        @endif
        @if ($canUpdate)
            <button type="submit" name="action" value="update" class="btn-primary btn-sm"><x-icon name="check" class="h-4 w-4" /> Appliquer</button>
        @endif
    </div>
</form>
