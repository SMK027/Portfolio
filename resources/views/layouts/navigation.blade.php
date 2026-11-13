@php
    $user = auth()->user();
    $unread = $user->can('panel', 'messages.read') ? \App\Models\ContactMessage::whereNull('read_at')->count() : 0;
    $maintenanceActive = $user->can('panel', 'maintenance.read|maintenance.manage') && app(\App\Services\Maintenance::class)->isActive();

    $waitingRoom = $user->can('panel', 'waiting_room.read|waiting_room.manage') ? app(\App\Services\WaitingRoom::class) : null;
    $waitingRoomQueueing = $waitingRoom?->isEnabled() && $waitingRoom->isQueueing();

    $activeBans = $user->isSuperAdmin() ? \App\Models\IpBan::active()->count() : 0;
    $pendingRecommendations = $user->can('panel', 'recommendations.read|recommendations.write')
        ? \App\Models\Recommendation::where('status', \App\Models\Recommendation::PENDING)->count() : 0;
    $pendingArticles = $user->isAdmin() ? \App\Models\Article::pendingReview()->count() : 0;
    $pendingAppointments = $user->can('panel', 'appointments.read|appointments.write')
        ? \App\Models\Appointment::where('status', 'pending')->where('starts_at', '>=', now())->count() : 0;

    // Administrateurs : tout ; bots et personnel : sections couvertes par leurs autorisations ;
    // contributeurs : rédaction d'articles.
    $sections = match (true) {
        $user->isAdmin()       => (function () use ($user) {
            $sections = \App\Support\PanelSections::for($user);
            $sections['Suivi'] = [['admin.dashboard', 'Tableau de bord', 'home', 'admin.dashboard'], ...($sections['Suivi'] ?? [])];

            return ['Suivi' => $sections['Suivi']] + $sections;
        })(),
        $user->hasLimitedAccess() => \App\Support\PanelSections::for($user),
        $user->isContributor() => ['Rédaction' => [['admin.articles.index', 'Articles de veille', 'newspaper', 'admin.articles.*']]],
        default                => [],
    };

    // Réservé aux super-administrateurs
    if ($user->isSuperAdmin()) {
        $sections['Comptes et sécurité'] = [
            ...($sections['Comptes et sécurité'] ?? []),
            ['admin.service-accounts.index', 'Comptes de service et bots', 'cog', 'admin.service-accounts.*'],
            ['admin.supervisors.index', 'Superviseurs', 'lock', 'admin.supervisors.*'],
            ['admin.notifications.edit', 'Notifications Discord', 'megaphone', 'admin.notifications.*'],
            ['admin.sandbox.show', 'Machine SSH', 'terminal', 'admin.sandbox.*'],
            ['admin.login-path.edit', 'Adresse de connexion', 'lock', 'admin.login-path.*'],
            ['admin.ip-bans.index', 'Adresses IP bannies', 'no-symbol', 'admin.ip-bans.*'],
            ['admin.blocked-countries.edit', 'Pays bloqués', 'no-symbol', 'admin.blocked-countries.*'],
            ['admin.ip-history', 'Historique d\'une IP', 'search', 'admin.ip-history'],
            ['admin.audit.index', 'Journal d\'activité', 'clock', 'admin.audit.*'],
            ['admin.diagnostics', 'Diagnostic', 'wrench', 'admin.diagnostics*'],
        ];
    }

    // Compteurs affichés sur les liens… et sur le titre d'un groupe replié.
    $badges = array_filter([
        'admin.articles.index'     => $pendingArticles ? [$pendingArticles, 'bg-amber-400 text-amber-950', 'Articles à valider'] : null,
        'admin.appointments.index' => $pendingAppointments ? [$pendingAppointments, 'bg-amber-400 text-amber-950', 'Demandes à confirmer'] : null,
        'admin.recommendations.index' => $pendingRecommendations ? [$pendingRecommendations, 'bg-amber-400 text-amber-950', 'Recommandations à modérer'] : null,
        'admin.messages.index'     => $unread ? [$unread, 'bg-primary-500 text-white', 'Messages non lus'] : null,
        'admin.ip-bans.index'      => $activeBans ? [$activeBans, 'bg-red-500 text-white', 'Adresses IP bannies'] : null,
        'admin.maintenance.edit'   => $maintenanceActive ? ['Active', 'bg-amber-400 text-amber-950', 'Maintenance active'] : null,
        'admin.waiting-room.edit'  => $waitingRoomQueueing ? ['Active', 'bg-amber-400 text-amber-950', 'File d\'attente en cours'] : null,
    ]);
@endphp

{{-- Commandes de la palette Ctrl+K (layouts/partials/admin-palette) : les liens de ce menu --}}
@php
    $commands = collect($sections)->flatMap(fn ($items, $section) => collect($items)->map(fn ($item) => [
        'label' => $item[1], 'group' => $section, 'icon' => $item[2], 'url' => route($item[0]),
    ]))->values()->all();
    if (! $user->isMachine()) {
        $commands[] = ['label' => 'Mon compte', 'group' => 'Compte', 'icon' => 'cog', 'url' => route('profile.edit')];
    }
    $commands[] = ['label' => 'Voir le site', 'group' => 'Compte', 'icon' => 'external', 'url' => url('/'), 'external' => true];
@endphp
<script type="application/json" id="admin-commands">@json($commands)</script>

<div class="flex h-16 flex-none items-center justify-between gap-2 border-b border-white/10 px-5">
    <a href="{{ route('dashboard') }}" class="font-display text-lg font-bold text-white">Portfolio<span class="text-primary-400">.</span>admin</a>
    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 lg:hidden" @click="sidebar = false" aria-label="Fermer le menu">
        <x-icon name="x" class="h-5 w-5" />
    </button>
</div>

<nav class="flex-1 space-y-3 overflow-y-auto px-3 py-5" aria-label="Administration">
    {{-- Groupes thématiques repliables : celui de la page en cours s'ouvre, l'état est mémorisé --}}
    @foreach ($sections as $section => $items)
        @php
            $groupActive = collect($items)->contains(fn ($item) => request()->routeIs($item[3]));
            $groupBadges = collect($items)->map(fn ($item) => $badges[$item[0]] ?? null)->filter();
            $groupKey = \Illuminate\Support\Str::slug($section);
        @endphp
        <div @if ($section) x-data="{
                 open: @js($groupActive) || (() => { try { return localStorage.getItem('nav.{{ $groupKey }}') === '1' } catch (e) { return false } })(),
                 remember() { try { localStorage.setItem('nav.{{ $groupKey }}', this.open ? '1' : '0') } catch (e) {} },
             }" x-effect="remember()" @endif>
            @if ($section)
                <button type="button" @click="open = ! open" :aria-expanded="open.toString()"
                        class="mb-1 flex w-full items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500 hover:bg-white/5 hover:text-slate-300">
                    <span class="flex-1 text-left">{{ $section }}</span>
                    @foreach ($groupBadges as [$count, $classes, $title])
                        <span x-show="! open" class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold normal-case tracking-normal {{ $classes }}" title="{{ $title }}">{{ $count }}</span>
                    @endforeach
                    <x-icon name="chevron-right" class="h-3.5 w-3.5 transition" ::class="open && 'rotate-90'" />
                </button>
            @endif
            <ul class="space-y-0.5" @if ($section) x-show="open" @unless ($groupActive) x-cloak @endunless @endif>
                @foreach ($items as [$route, $label, $icon, $pattern])
                    @php $active = request()->routeIs($pattern); @endphp
                    <li>
                        <a href="{{ route($route) }}" @class([
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                            'bg-white/10 text-white' => $active,
                            'hover:bg-white/5 hover:text-white' => ! $active,
                        ]) @if ($active) aria-current="page" @endif>
                            <x-icon :name="$icon" class="h-5 w-5 flex-none {{ $active ? 'text-primary-300' : 'text-slate-500' }}" />
                            <span class="flex-1">{{ $label }}</span>
                            @if ($badge = $badges[$route] ?? null)
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[1] }}" title="{{ $badge[2] }}">{{ $badge[0] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach

    <div>
        <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Compte</p>
        <ul class="space-y-0.5">
            @unless ($user->isMachine())
            <li>
                <a href="{{ route('profile.edit') }}" @class([
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                    'bg-white/10 text-white' => request()->routeIs('profile.*'),
                    'hover:bg-white/5 hover:text-white' => ! request()->routeIs('profile.*'),
                ])>
                    <x-icon name="cog" class="h-5 w-5 text-slate-500" /> Mon compte
                </a>
            </li>
            @endunless
            <li>
                <a href="{{ url('/') }}" target="_blank" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-white/5 hover:text-white">
                    <x-icon name="external" class="h-5 w-5 text-slate-500" /> Voir le site
                </a>
            </li>
        </ul>
    </div>
</nav>

<div class="flex-none border-t border-white/10 p-4">
    <div class="flex items-center justify-between gap-2">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
            <p class="truncate text-xs text-slate-500">{{ $user->roleLabel() }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white" title="Se déconnecter" aria-label="Se déconnecter">
                <x-icon name="logout" class="h-5 w-5" />
            </button>
        </form>
    </div>
</div>
