<x-app-layout>
    <x-slot name="title">Statistiques — temps réel</x-slot>
    <x-slot name="header">Statistiques de visite</x-slot>

    @include('admin.statistics._tabs', ['tab' => 'live'])

    {{-- Page 1 : les nouvelles pages vues s'ajoutent en tête toutes les 5 secondes. --}}
    <div x-data="{
            lastId: {{ (int) ($views->first()?->id ?? 0) }},
            active: {{ $online->count() }},
            paused: false,
            fresh: 0,
            live: {{ $views->onFirstPage() ? 'true' : 'false' }},
            init() { if (this.live) setInterval(() => this.poll(), 5000) },
            async poll() {
                if (this.paused || document.hidden) return;
                try {
                    const r = await fetch(@js(route('admin.statistics', ['onglet' => 'temps-reel'])) + '&depuis=' + this.lastId, { headers: { Accept: 'application/json' } });
                    if (! r.ok) return;
                    const data = await r.json();
                    this.active = data.active;
                    this.$refs.online.innerHTML = data.online;
                    if (! data.count) return;
                    this.lastId = data.last_id;
                    this.fresh += data.count;
                    this.$refs.empty?.remove();
                    const tmp = document.createElement('tbody');
                    tmp.innerHTML = data.html;
                    [...tmp.children].reverse().forEach(tr => { tr.classList.add('bg-emerald-50'); this.$refs.rows.prepend(tr); setTimeout(() => tr.classList.remove('bg-emerald-50'), 4000); });
                } catch (e) {}
            },
         }" class="space-y-6">

        <div class="flex flex-wrap items-center gap-4">
            <div class="card flex items-center gap-3 px-5 py-4">
                <span class="relative flex h-3 w-3"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" x-show="live && ! paused"></span><span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span></span>
                <div>
                    <p class="font-display text-2xl font-bold text-slate-900" x-text="active">{{ $online->count() }}</p>
                    <p class="text-xs text-slate-500">visiteur(s) en ligne en ce moment</p>
                </div>
            </div>
            <template x-if="live">
                <div class="flex items-center gap-3 text-sm text-slate-500">
                    <button type="button" class="btn-secondary" @click="paused = ! paused" x-text="paused ? 'Reprendre' : 'Mettre en pause'"></button>
                    <span x-show="fresh" x-text="fresh + ' nouvelle(s) page(s) vue(s) depuis l\'ouverture'"></span>
                </div>
            </template>
            @unless ($views->onFirstPage())
                <a href="{{ route('admin.statistics', ['onglet' => 'temps-reel']) }}" class="text-sm font-medium text-primary-600 underline">Revenir au direct</a>
            @endunless
        </div>

        <x-admin.section title="En ce moment">
            <p class="text-sm text-slate-500">
                Pages ouvertes et visibles : la page signale sa présence toutes les 30 secondes tant qu'elle est affichée,
                et son départ quand elle est fermée ou passe en arrière-plan. Sans signal depuis {{ \App\Services\SiteStatistics::ONLINE_SECONDS }} secondes, le visiteur n'est plus compté.
            </p>
            <div x-ref="online">@include('admin.statistics._online')</div>
        </x-admin.section>

        <x-admin.section title="Trafic, du plus récent au plus ancien">
            <div class="-mx-4 overflow-x-auto sm:-mx-6">
                <table class="admin-table">
                    <thead><tr><th>Date</th>@if ($showIps)<th>IP</th>@endif<th>Pays</th><th>Appareil</th><th>Page</th><th>Visiteur</th><th class="text-right">Durée</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 [&>tr]:transition-colors [&>tr]:duration-1000" x-ref="rows">
                        @forelse ($views as $view)
                            @include('admin.statistics._row', ['dateFormat' => 'd/m/Y H:i:s'])
                        @empty
                            <tr x-ref="empty"><td colspan="7" class="text-sm text-slate-500">Aucune visite enregistrée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $views->links() }}
        </x-admin.section>
    </div>
</x-app-layout>
