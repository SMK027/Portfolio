@php $days = $contact->followUpInDays(); @endphp
@if ($contact->followed_up_on || $days !== null)
    @if ($contact->followed_up_on)
        <p class="text-slate-700" title="Dernière relance"><span class="lg:hidden text-slate-500">Relancé le </span>{{ $contact->followed_up_on->format('d/m/Y') }}</p>
    @endif
    @if ($days !== null)
        <p @class(['text-xs', 'font-semibold text-red-600' => $days <= 0, 'font-medium text-amber-700' => $days > 0 && $days <= 3, 'text-slate-500' => $days > 3]) title="Relance prévue le {{ $contact->follow_up_on->format('d/m/Y') }}">
            Prévue {{ $contact->follow_up_on->format('d/m') }} · {{ $contact->followUpHint() }}
        </p>
    @endif
@else
    <span class="hidden text-slate-300 lg:inline" aria-label="Aucune relance">—</span>
@endif
