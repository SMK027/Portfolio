@props(['article'])
@php
    $status = $article->status();
    $classes = match ($status) {
        'Publié'                   => 'badge-green',
        'Programmé'                => 'badge-primary',
        'En attente de validation' => 'badge-amber',
        'À retravailler'           => 'badge bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
        default                    => 'badge-slate',
    };
@endphp
<span {{ $attributes->merge(['class' => $classes]) }}>{{ $status }}</span>
