@props(['cancel' => null, 'label' => 'Enregistrer'])
<div class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
    {{ $slot }}
    @if ($cancel)
        <a href="{{ $cancel }}" class="btn-secondary">Annuler</a>
    @endif
    <button type="submit" class="btn-primary"><x-icon name="check" class="h-4 w-4" /> {{ $label }}</button>
</div>
