@if ($contact->isAwaiting() && $contact->follow_up_on)
    @php $days = $contact->followUpInDays(); @endphp
    <p @class(['text-sm', 'font-semibold text-red-600' => $days <= 0, 'text-amber-700' => $days > 0 && $days <= 3, 'text-slate-900' => $days > 3])>
        {{ $contact->follow_up_on->format('d/m/Y') }}
    </p>
    <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500">
        @if ($contact->follow_up_method)<x-icon :name="$contact->methodIcon($contact->follow_up_method)" class="h-3.5 w-3.5" /> {{ $contact->followUpMethodLabel() }} ·@endif
        {{ $contact->followUpHint() }}
    </p>
@elseif ($contact->isAwaiting())
    <span class="text-xs italic text-slate-400">Non planifiée</span>
@else
    <span class="text-slate-300">—</span>
@endif
