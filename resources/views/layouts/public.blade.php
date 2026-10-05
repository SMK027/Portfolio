<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    {{-- Thème appliqué avant l'affichage (pas de flash) : choix mémorisé, sinon réglage de l'appareil --}}
    <script>
        (() => {
            let theme = null;
            try { theme = localStorage.getItem('theme'); } catch (e) {}
            const dark = theme ? theme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @php
        $siteName = $siteProfile->fullName();
        $pageTitle = $title ? $title.' — '.$siteName : $siteName.($siteProfile->headline ? ' — '.$siteProfile->headline : '');
        // URL canonique : sans paramètres, sauf la pagination
        $canonical = request()->url().(request()->integer('page') > 1 ? '?page='.request()->integer('page') : '');
        $metaDescription = \Illuminate\Support\Str::limit(strip_tags($description ?? $page?->intro ?? $siteProfile->headline ?? ''), 160);
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', config('app.locale')) === 'fr' ? 'fr_FR' : config('app.locale') }}">
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    @if ($image)<meta property="og:image" content="{{ url($image) }}">@endif
    <link rel="canonical" href="{{ $canonical }}">
    @if (! $indexable || ($page && ! $page->is_public))<meta name="robots" content="noindex, nofollow">@endif

    {{-- Polices chargées sans bloquer l'affichage (font-display: swap) --}}
    @php $fontsUrl = 'https://fonts.bunny.net/css?family=figtree:400,500,600,700|space-grotesk:500,600,700&display=swap'; @endphp
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="preload" as="style" href="{{ $fontsUrl }}">
    <link rel="stylesheet" href="{{ $fontsUrl }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ $fontsUrl }}"></noscript>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-full flex-col bg-slate-50 font-sans text-slate-800 antialiased"
      @if ($pageViewId = request()->attributes->get('page_view_uuid')) data-page-view="{{ $pageViewId }}" data-page-view-url="{{ route('stats.duration') }}" @endif>
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Aller au contenu</a>

    @if ($maintenance && ! $isAdmin && auth()->user()?->hasBotPermission('maintenance.bypass'))
        <div class="bg-amber-400 px-4 py-2 text-center text-xs font-medium text-amber-950">
            Maintenance active : ce bot voit le site, les visiteurs voient la page de maintenance.
        </div>
    @endif

    @if ($isAdmin)
        {{-- Barre d'administration : visible uniquement par les administrateurs connectés --}}
        <div class="bg-slate-900 text-xs text-slate-300">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-2 sm:px-6">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($maintenance)
                        <a href="{{ route('admin.maintenance.edit') }}" class="inline-flex items-center gap-1 rounded-full bg-amber-400/15 px-2 py-0.5 font-medium text-amber-300 hover:bg-amber-400/25">
                            <x-icon name="wrench" class="h-3.5 w-3.5" /> Maintenance active — les visiteurs voient la page de maintenance
                        </a>
                    @endif
                    @unless ($indexable)
                        <a href="{{ route('admin.seo.edit') }}" class="inline-flex items-center gap-1 rounded-full bg-red-400/15 px-2 py-0.5 font-medium text-red-300 hover:bg-red-400/25">
                            <x-icon name="eye" class="h-3.5 w-3.5" /> Site non indexé
                        </a>
                    @endunless
                    @if ($page && ! $page->is_public)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-400/15 px-2 py-0.5 font-medium text-amber-300">
                            <x-icon name="lock" class="h-3.5 w-3.5" /> Page privée — visible uniquement par les administrateurs
                        </span>
                    @else
                        <span>Connecté en tant que <strong class="text-white">{{ auth()->user()->name }}</strong></span>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('admin.dashboard') }}" class="font-semibold text-white hover:text-primary-300">Administration</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="hover:text-white">Déconnexion</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/85 backdrop-blur" @keydown.escape.window="open = false">
        <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6" aria-label="Navigation principale">
            <a href="{{ $homePage?->isAccessibleBy(auth()->user()) ? route('home') : ($navPages->first()?->url() ?? '#') }}" class="flex min-w-0 items-center gap-3">
                @if ($siteProfile->photoUrl())
                    <img src="{{ $siteProfile->photoUrl() }}" alt="" decoding="async" width="36" height="36" class="h-9 w-9 flex-none rounded-full object-cover ring-2 ring-primary-100">
                @else
                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white">{{ $siteProfile->initials() }}</span>
                @endif
                <span class="truncate font-display text-lg font-bold text-slate-900">{{ $siteName }}</span>
            </a>

            @php
                $isActive = fn ($p) => request()->routeIs(\Illuminate\Support\Str::beforeLast($p->routeName(), '.').'*');
                $privateDot = '<span class="h-1.5 w-1.5 flex-none rounded-full bg-amber-400" title="Page privée (visible par les administrateurs)" aria-label="page privée"></span>';
            @endphp
            <div class="hidden min-w-0 items-center gap-0.5 lg:flex">
                @foreach ($navGroups as $label => $pages)
                    @if ($pages->count() === 1)
                        @php $navPage = $pages->first(); $active = $isActive($navPage); @endphp
                        <a href="{{ $navPage->url() }}" @class([
                               'inline-flex items-center gap-1 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition',
                               'bg-primary-50 text-primary-700' => $active,
                               'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
                           ]) @if ($active) aria-current="page" @endif>
                            {{ $navPage->title }} @unless ($navPage->is_public){!! $privateDot !!}@endunless
                        </a>
                    @else
                        {{-- Sous-menu thématique --}}
                        @php $groupActive = $pages->contains(fn ($p) => $isActive($p)); @endphp
                        <div class="relative" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape="menu = false; $refs.trigger.focus()"
                             @mouseenter="menu = true" @mouseleave="menu = false">
                            <button type="button" x-ref="trigger" @click="menu = ! menu" :aria-expanded="menu.toString()" aria-haspopup="true"
                                    @class([
                                        'inline-flex items-center gap-1 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition',
                                        'bg-primary-50 text-primary-700' => $groupActive,
                                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $groupActive,
                                    ])>
                                {{ $label }}
                                <span class="transition" :class="menu && 'rotate-90'"><x-icon name="chevron-right" class="h-3.5 w-3.5" /></span>
                            </button>
                            <div x-show="menu" x-cloak x-transition.origin.top.left class="absolute left-0 top-full z-50 pt-1">
                                <ul class="min-w-[14rem] rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                                    @foreach ($pages as $navPage)
                                        @php $active = $isActive($navPage); @endphp
                                        <li>
                                            <a href="{{ $navPage->url() }}" @class([
                                                   'flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                                   'bg-primary-50 text-primary-700' => $active,
                                                   'text-slate-700 hover:bg-slate-100 hover:text-slate-900' => ! $active,
                                               ]) @if ($active) aria-current="page" @endif>
                                                {{ $navPage->title }} @unless ($navPage->is_public){!! $privateDot !!}@endunless
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="ml-1 flex flex-none items-center gap-1">
            {{-- Thème clair / sombre --}}
            <button type="button" x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    @click="dark = ! dark; document.documentElement.classList.toggle('dark', dark); try { localStorage.setItem('theme', dark ? 'dark' : 'light') } catch (e) {}"
                    class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900" :aria-label="dark ? 'Passer en mode clair' : 'Passer en mode sombre'" :title="dark ? 'Mode clair' : 'Mode sombre'">
                <x-icon name="moon" class="h-5 w-5" x-show="! dark" />
                <x-icon name="sun" class="h-5 w-5" x-show="dark" x-cloak />
            </button>
            {{-- Recherche globale (Ctrl+K ou /) --}}
            <button type="button" @click="$dispatch('open-search')" class="inline-flex items-center gap-2 rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900" aria-label="Rechercher (Ctrl+K)" title="Rechercher (Ctrl+K)">
                <x-icon name="search" class="h-5 w-5" />
            </button>
            <button type="button" @click="open = !open" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                    :aria-expanded="open.toString()" aria-controls="menu-mobile" aria-label="Ouvrir le menu">
                <x-icon name="menu" class="h-6 w-6" x-show="!open" />
                <x-icon name="x" class="h-6 w-6" x-show="open" x-cloak />
            </button>
            </div>
        </nav>

        <div id="menu-mobile" x-show="open" x-cloak x-transition.origin.top class="border-t border-slate-200 bg-white lg:hidden">
            <div class="mx-auto max-w-6xl space-y-3 px-4 py-3 sm:px-6">
                @foreach ($navGroups as $label => $pages)
                    <div>
                        @if ($pages->count() > 1)<p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</p>@endif
                        @foreach ($pages as $navPage)
                            <a href="{{ $navPage->url() }}" @class(['flex items-center justify-between rounded-lg px-3 py-2.5 text-base font-medium hover:bg-slate-100', 'text-primary-700' => $isActive($navPage), 'text-slate-700' => ! $isActive($navPage)])>
                                {{ $navPage->title }}
                                @unless ($navPage->is_public)
                                    <span class="badge-amber"><x-icon name="lock" class="h-3 w-3" /> Privée</span>
                                @endunless
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </header>

    @include('partials.search-palette')

    <x-announcements :announcements="$announcements" />

    <main id="contenu" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-10 sm:px-6 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="font-display text-lg font-bold text-slate-900">{{ $siteName }}</p>
                @if ($siteProfile->headline)<p class="text-sm text-slate-500">{{ $siteProfile->headline }}</p>@endif
            </div>
            <div class="flex items-center gap-2">
                <x-social-links :profile="$siteProfile" />
            </div>
        </div>
        <div class="border-t border-slate-100">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-slate-400 sm:px-6">
                <span>© {{ now()->year }} {{ $siteName }}</span>
                @guest
                    <a href="{{ route('login') }}" class="hover:text-slate-600">Espace administrateur</a>
                @endguest
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
