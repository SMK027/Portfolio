{{-- Onglets de la page de statistiques. --}}
<div class="flex gap-1 border-b border-slate-200 text-sm" role="tablist">
    @foreach (['overview' => ['Vue d\'ensemble', route('admin.statistics')], 'live' => ['Temps réel', route('admin.statistics', ['onglet' => 'temps-reel'])]] as $key => [$label, $href])
        <a href="{{ $href }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
           @class(['-mb-px border-b-2 px-4 py-2 font-medium', 'border-primary-600 text-primary-700' => $tab === $key, 'border-transparent text-slate-500 hover:text-slate-900' => $tab !== $key])>
            @if ($key === 'live')<span class="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-500"></span>@endif{{ $label }}
        </a>
    @endforeach
</div>
