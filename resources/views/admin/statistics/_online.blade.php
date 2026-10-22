{{-- Visiteurs en ligne et page consultée (onglet « Temps réel », rafraîchi par interrogation). --}}
@if ($online->isEmpty())
    <p class="text-sm text-slate-500">Aucun visiteur en ligne.</p>
@else
    <ul class="divide-y divide-slate-100">
        @foreach ($online as $view)
            <li class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2 text-sm">
                <span class="h-2 w-2 flex-none rounded-full bg-emerald-500" aria-hidden="true"></span>
                <a href="{{ url($view->path) }}" target="_blank" class="min-w-0 flex-1 truncate font-medium text-slate-900 hover:text-primary-700">{{ $view->path }}</a>
                <span class="whitespace-nowrap text-slate-600">{{ \App\Services\GeoIp::label($view->country) }}@if ($view->city) · {{ $view->city }}@endif</span>
                <span class="whitespace-nowrap text-slate-500">{{ \App\Support\UserAgent::DEVICES[$view->device] ?? '—' }}@if ($view->browser) · {{ $view->browser }}@endif</span>
                @if ($showIps && $view->ip_address)<span class="font-mono text-xs text-slate-500">{{ $view->ip_address }}</span>@endif
                <span class="whitespace-nowrap text-xs text-slate-400">sur la page depuis {{ $view->created_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
            </li>
        @endforeach
    </ul>
@endif
