@props(['page' => null, 'title' => null, 'intro' => null, 'eyebrow' => null, 'background' => null])
<section @class([
    'relative overflow-hidden',
    'bg-gradient-to-br from-slate-900 via-slate-800 to-primary-900' => ! $background,
    'bg-slate-900' => $background,
])>
    @if ($background)
        <img src="{{ $background }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/50 to-slate-900/30"></div>
    @else
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-accent-500/10 blur-3xl"></div>
    @endif
    <div class="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
        @if ($eyebrow)
            <div class="mb-3 text-sm font-medium text-primary-200">{{ $eyebrow }}</div>
        @endif
        <h1 class="font-display text-3xl font-bold tracking-tight text-white sm:text-5xl">{{ $title ?? $page?->title }}</h1>
        @if ($intro ?? $page?->intro)
            <p class="mt-4 max-w-2xl text-base text-slate-300 sm:text-lg">{{ $intro ?? $page?->intro }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
