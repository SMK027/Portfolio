{{-- Ligne d'une page vue (onglets « Vue d'ensemble » et « Temps réel »). --}}
<tr data-id="{{ $view->id }}">
    <td class="whitespace-nowrap text-xs text-slate-500">{{ $view->created_at?->format($dateFormat ?? 'd/m H:i') }}</td>
    @if ($showIps)<td class="font-mono text-xs">@if ($view->ip_address && auth()->user()->can('view-audit-log'))<a href="{{ route('admin.ip-history', ['ip' => $view->ip_address]) }}" class="hover:text-primary-700 hover:underline" title="Historique de cette IP">{{ $view->ip_address }}</a>@else{{ $view->ip_address ?? '—' }}@endif</td>@endif
    <td class="whitespace-nowrap text-sm">{{ \App\Services\GeoIp::label($view->country) }}@if ($view->city)<span class="block text-xs text-slate-400">{{ $view->city }}</span>@endif</td>
    <td class="whitespace-nowrap text-sm">{{ \App\Support\UserAgent::DEVICES[$view->device] ?? '—' }}<span class="block text-xs text-slate-400">{{ collect([$view->browser, $view->os])->filter()->join(' · ') }}</span></td>
    <td class="max-w-xs truncate text-sm"><a href="{{ url($view->path) }}" target="_blank" class="hover:text-primary-700">{{ $view->path }}</a></td>
    <td class="text-sm">@if ($view->visitor_id)<span @class(['badge-green' => $view->is_new_visitor, 'badge-slate' => ! $view->is_new_visitor])>{{ $view->is_new_visitor ? 'Nouveau' : 'Récurrent' }}</span>@else — @endif</td>
    <td class="whitespace-nowrap text-right text-sm">{{ \App\Services\SiteStatistics::duration($view->duration) }}</td>
</tr>
