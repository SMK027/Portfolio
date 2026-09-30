@props(['href', 'label' => 'Modifier', 'can' => null])
{{-- can : autorisation de modifier ; sans elle, le lien devient « Consulter » --}}
@php $readOnly = $can && ! auth()->user()?->can('panel', $can); $label = $readOnly ? 'Consulter' : $label; @endphp
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center align-middle rounded-lg p-2 text-slate-400 hover:bg-primary-50 hover:text-primary-600']) }} title="{{ $label }}" aria-label="{{ $label }}">
    <x-icon :name="$readOnly ? 'eye' : 'pencil'" class="h-4 w-4" />
</a>
