{{-- Liens vers les réseaux sociaux de la présentation --}}
@props(['profile', 'iconClass' => 'h-5 w-5', 'linkClass' => 'rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900'])
@foreach ($profile->socialLinks() as $link)
    <a href="{{ $link['url'] }}" target="_blank" rel="noopener me" class="{{ $linkClass }}" title="{{ $link['name'] }}" aria-label="{{ $link['name'] }}">
        <x-icon :name="$link['icon']" :class="$iconClass" />
    </a>
@endforeach
