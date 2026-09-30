@props(['cancel' => null, 'label' => 'Enregistrer', 'can' => null])
{{-- can : autorisation requise pour enregistrer (sinon formulaire en lecture seule) --}}
@if ($can && ! auth()->user()?->can('panel', $can))
    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        <span><x-icon name="eye" class="mr-1 inline h-4 w-4" /> Lecture seule : ce compte n'est pas autorisé à enregistrer des modifications.</span>
        @if ($cancel)<a href="{{ $cancel }}" class="btn-secondary btn-sm">Retour</a>@endif
    </div>
@else
<div class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
    {{ $slot }}
    @if ($cancel)
        <a href="{{ $cancel }}" class="btn-secondary">Annuler</a>
    @endif
    <button type="submit" class="btn-primary"><x-icon name="check" class="h-4 w-4" /> {{ $label }}</button>
</div>
@endif
