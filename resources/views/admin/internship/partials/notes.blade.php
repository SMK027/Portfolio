@if ($contact->notes)
    @php $long = mb_strlen($contact->notes) > 110 || substr_count($contact->notes, "\n") > 1; @endphp
    <div x-data="{ open: false }" class="text-xs leading-relaxed text-slate-600">
        <p class="whitespace-pre-line [overflow-wrap:anywhere]" @if ($long) :class="open || 'line-clamp-2'" @endif>{{ \App\Support\TextLinks::render($contact->notes) }}</p>
        @if ($long)
            <button type="button" class="font-medium text-primary-600 hover:underline" @click="open = ! open" x-text="open ? 'Réduire' : 'Lire la suite'"></button>
        @endif
    </div>
@else
    <span class="hidden text-slate-300 lg:inline">—</span>
@endif
