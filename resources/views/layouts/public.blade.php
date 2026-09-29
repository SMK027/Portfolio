<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $siteName = $siteProfile->fullName();
        $pageTitle = $title ? $title.' — '.$siteName : $siteName.($siteProfile->headline ? ' — '.$siteProfile->headline : '');
        $metaDescription = \Illuminate\Support\Str::limit(strip_tags($description ?? $page?->intro ?? $siteProfile->headline ?? ''), 160);
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    @if ($image)<meta property="og:image" content="{{ url($image) }}">@endif
    @if ($page && ! $page->is_public)<meta name="robots" content="noindex, nofollow">@endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|space-grotesk:500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-full flex-col bg-slate-50 font-sans text-slate-800 antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Aller au contenu</a>

    @if ($isAdmin)
        {{-- Barre d'administration : visible uniquement par les administrateurs connectés --}}
        <div class="bg-slate-900 text-xs text-slate-300">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-2 sm:px-6">
                <div class="flex items-center gap-2">
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
                    <img src="{{ $siteProfile->photoUrl() }}" alt="" class="h-9 w-9 flex-none rounded-full object-cover ring-2 ring-primary-100">
                @else
                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-accent-500 text-sm font-bold text-white">{{ $siteProfile->initials() }}</span>
                @endif
                <span class="truncate font-display text-lg font-bold text-slate-900">{{ $siteName }}</span>
            </a>

            <div class="hidden items-center gap-1 lg:flex">
                @foreach ($navPages as $navPage)
                    @php $active = request()->routeIs(\Illuminate\Support\Str::beforeLast($navPage->routeName(), '.').'*'); @endphp
                    <a href="{{ $navPage->url() }}"
                       @class([
                           'inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium transition',
                           'bg-primary-50 text-primary-700' => $active,
                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
                       ])
                       @if ($active) aria-current="page" @endif>
                        {{ $navPage->title }}
                        @unless ($navPage->is_public)
                            <x-icon name="lock" class="h-3.5 w-3.5 text-amber-500" title="Page privée" />
                        @endunless
                    </a>
                @endforeach
            </div>

            <button type="button" @click="open = !open" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                    :aria-expanded="open.toString()" aria-controls="menu-mobile" aria-label="Ouvrir le menu">
                <x-icon name="menu" class="h-6 w-6" x-show="!open" />
                <x-icon name="x" class="h-6 w-6" x-show="open" x-cloak />
            </button>
        </nav>

        <div id="menu-mobile" x-show="open" x-cloak x-transition.origin.top class="border-t border-slate-200 bg-white lg:hidden">
            <div class="mx-auto max-w-6xl space-y-1 px-4 py-3 sm:px-6">
                @foreach ($navPages as $navPage)
                    <a href="{{ $navPage->url() }}" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-100">
                        {{ $navPage->title }}
                        @unless ($navPage->is_public)
                            <span class="badge-amber"><x-icon name="lock" class="h-3 w-3" /> Privée</span>
                        @endunless
                    </a>
                @endforeach
            </div>
        </div>
    </header>

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
                @if ($siteProfile->github_url)
                    <a href="{{ $siteProfile->github_url }}" target="_blank" rel="noopener" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="GitHub"><x-icon name="github" /></a>
                @endif
                @if ($siteProfile->linkedin_url)
                    <a href="{{ $siteProfile->linkedin_url }}" target="_blank" rel="noopener" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="LinkedIn"><x-icon name="linkedin" /></a>
                @endif
                @if ($siteProfile->website_url)
                    <a href="{{ $siteProfile->website_url }}" target="_blank" rel="noopener" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="Site web"><x-icon name="globe" /></a>
                @endif
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
