@if ($contact->isAwaiting() && $contact->follow_up_on)
    @php $days = $contact->followUpInDays(); @endphp
    <p class="flex items-center gap-1.5">
        @if ($contact->follow_up_method)<x-icon :name="$contact->methodIcon($contact->follow_up_method)" class="h-4 w-4 flex-none text-slate-400" />@endif
        <span @class(['font-semibold text-red-600' => $days <= 0, 'font-medium text-amber-700' => $days > 0 && $days <= 3, 'text-slate-700' => $days > 3])>{{ $contact->follow_up_on->format('d/m/Y') }}</span>
    </p>
    <p @class(['text-xs', 'text-red-600' => $days <= 0, 'text-slate-500' => $days > 0])>{{ $contact->followUpHint() }}</p>
@else
    <span class="hidden text-slate-300 lg:inline" aria-label="Aucune relance">—</span>
@endif
