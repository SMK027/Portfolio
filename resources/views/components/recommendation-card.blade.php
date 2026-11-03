@props(['recommendation'])
<figure {{ $attributes->merge(['class' => 'card flex h-full flex-col p-6']) }}>
    <span class="font-display text-5xl leading-none text-primary-200" aria-hidden="true">“</span>
    <blockquote class="-mt-3 flex-1 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $recommendation->content }}</blockquote>
    <figcaption class="mt-6 flex items-center gap-3 border-t border-slate-100 pt-4">
        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white">{{ $recommendation->initials() }}</span>
        <span class="min-w-0">
            <span class="block font-semibold text-slate-900">
                @if ($recommendation->author_url)
                    <a href="{{ $recommendation->author_url }}" target="_blank" rel="noopener noreferrer nofollow" class="hover:text-primary-600">{{ $recommendation->author_name }}</a>
                @else
                    {{ $recommendation->author_name }}
                @endif
            </span>
            @if ($recommendation->authorTitle())<span class="block text-xs text-slate-500">{{ $recommendation->authorTitle() }}</span>@endif
            @if ($recommendation->relationship)<span class="block text-xs text-slate-400">{{ $recommendation->relationship }}</span>@endif
        </span>
    </figcaption>
</figure>
