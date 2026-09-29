@props(['href', 'label' => 'Modifier'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'rounded-lg p-2 text-slate-400 hover:bg-primary-50 hover:text-primary-600']) }} title="{{ $label }}" aria-label="{{ $label }}">
    <x-icon name="pencil" class="h-4 w-4" />
</a>
