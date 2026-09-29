@php
    $user = auth()->user();
    $unread = $user->isAdmin() ? \App\Models\ContactMessage::whereNull('read_at')->count() : 0;

    $sections = $user->isAdmin() ? [
        '' => [
            ['admin.dashboard', 'Tableau de bord', 'squares', 'admin.dashboard'],
        ],
        'Contenu' => [
            ['admin.profile.edit', 'Présentation', 'user', 'admin.profile.*'],
            ['admin.formations.index', 'Formations', 'academic-cap', 'admin.formations.*'],
            ['admin.diplomes.index', 'Diplômes', 'diploma', 'admin.diplomes.*'],
            ['admin.certifications.index', 'Certifications', 'badge', 'admin.certifications.*'],
            ['admin.competences.index', 'Compétences', 'sparkles', 'admin.competences.*'],
            ['admin.themes.index', 'Thèmes', 'tag', 'admin.themes.*'],
            ['admin.projets.index', 'Projets', 'folder', 'admin.projets.*'],
            ['admin.articles.index', 'Veille', 'newspaper', 'admin.articles.*'],
        ],
        'Site' => [
            ['admin.annonces.index', 'Annonces', 'megaphone', 'admin.annonces.*'],
            ['admin.messages.index', 'Messages', 'inbox', 'admin.messages.*'],
            ['admin.pages.index', 'Pages & visibilité', 'eye', 'admin.pages.*'],
            ['admin.seo.edit', 'Référencement', 'globe', 'admin.seo.*'],
            ['admin.utilisateurs.index', 'Comptes', 'users', 'admin.utilisateurs.*'],
        ],
    ] : [];
@endphp

<div class="flex h-16 flex-none items-center justify-between gap-2 border-b border-white/10 px-5">
    <a href="{{ route('dashboard') }}" class="font-display text-lg font-bold text-white">Portfolio<span class="text-primary-400">.</span>admin</a>
    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 lg:hidden" @click="sidebar = false" aria-label="Fermer le menu">
        <x-icon name="x" class="h-5 w-5" />
    </button>
</div>

<nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Administration">
    @foreach ($sections as $section => $items)
        <div>
            @if ($section)
                <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $section }}</p>
            @endif
            <ul class="space-y-0.5">
                @foreach ($items as [$route, $label, $icon, $pattern])
                    @php $active = request()->routeIs($pattern); @endphp
                    <li>
                        <a href="{{ route($route) }}" @class([
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                            'bg-white/10 text-white' => $active,
                            'hover:bg-white/5 hover:text-white' => ! $active,
                        ])>
                            <x-icon :name="$icon" class="h-5 w-5 flex-none {{ $active ? 'text-primary-300' : 'text-slate-500' }}" />
                            <span class="flex-1">{{ $label }}</span>
                            @if ($route === 'admin.messages.index' && $unread)
                                <span class="rounded-full bg-primary-500 px-2 py-0.5 text-xs font-semibold text-white">{{ $unread }}</span>
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
            <li>
                <a href="{{ route('profile.edit') }}" @class([
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                    'bg-white/10 text-white' => request()->routeIs('profile.*'),
                    'hover:bg-white/5 hover:text-white' => ! request()->routeIs('profile.*'),
                ])>
                    <x-icon name="cog" class="h-5 w-5 text-slate-500" /> Mon compte
                </a>
            </li>
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
