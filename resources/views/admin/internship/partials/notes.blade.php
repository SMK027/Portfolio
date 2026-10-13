@if ($contact->notes)
    @php $long = mb_strlen($contact->notes) > 140 || substr_count($contact->notes, "\n") > 2; @endphp
    <div x-data="{ open: false }" class="text-xs leading-relaxed text-slate-600">
        <p class="whitespace-pre-line" @if ($long) :class="open || 'line-clamp-3'" @endif>{{ $contact->notes }}</p>
        @if ($long)
            <button type="button" class="mt-1 font-medium text-primary-600 hover:underline" @click="open = ! open" x-text="open ? 'Réduire' : 'Lire la suite'"></button>
        @endif
    </div>
@else
    <span class="text-slate-300">—</span>
@endif
