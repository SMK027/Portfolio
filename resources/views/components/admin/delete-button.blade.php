@props(['action', 'confirm' => 'Supprimer définitivement cet élément ?', 'label' => 'Supprimer', 'can' => null])
{{-- can : autorisation requise ; le bouton est masqué sans elle --}}
@if (! $can || auth()->user()?->can('panel', $can))
<form method="POST" action="{{ $action }}" onsubmit="return confirm(@js($confirm))" class="inline-flex align-middle">
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600']) }} title="{{ $label }}" aria-label="{{ $label }}">
        <x-icon name="trash" class="h-4 w-4" />
    </button>
</form>
@endif
