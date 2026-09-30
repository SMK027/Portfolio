@props(['kind'])
@php
    $styles = [
        'pdf'     => ['bg-red-50 text-red-600', 'document'],
        'text'    => ['bg-blue-50 text-blue-600', 'document'],
        'sheet'   => ['bg-emerald-50 text-emerald-600', 'table'],
        'slides'  => ['bg-orange-50 text-orange-600', 'presentation'],
        'drawing' => ['bg-purple-50 text-purple-600', 'photo'],
        'image'   => ['bg-sky-50 text-sky-600', 'photo'],
        'archive' => ['bg-amber-50 text-amber-700', 'archive'],
    ];
    [$classes, $icon] = $styles[$kind] ?? ['bg-slate-100 text-slate-600', 'document'];
@endphp
<span {{ $attributes->merge(['class' => "flex h-10 w-10 flex-none items-center justify-center rounded-lg $classes"]) }}>
    <x-icon :name="$icon" class="h-5 w-5" />
</span>
