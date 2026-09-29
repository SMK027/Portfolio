@props(['title' => null, 'description' => null])
<section {{ $attributes->merge(['class' => 'card p-4 sm:p-6']) }}>
    @if ($title)
        <header class="mb-5">
            <h2 class="font-display text-base font-semibold text-slate-900">{{ $title }}</h2>
            @if ($description)<p class="mt-1 text-sm text-slate-500">{{ $description }}</p>@endif
        </header>
    @endif
    <div class="space-y-5">{{ $slot }}</div>
</section>
