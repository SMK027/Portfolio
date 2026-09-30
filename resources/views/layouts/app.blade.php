<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ isset($title) ? $title.' — ' : '' }}Administration — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|space-grotesk:500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">
    <div x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false" class="min-h-full">
        {{-- Voile mobile --}}
        <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="sidebar = false"></div>

        {{-- Barre latérale --}}
        <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-300 transition-transform duration-200 lg:translate-x-0">
            @include('layouts.navigation')
        </aside>

        <div class="flex min-h-full flex-col lg:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
                <button type="button" class="-ml-2 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" @click="sidebar = true" aria-label="Ouvrir le menu">
                    <x-icon name="menu" class="h-6 w-6" />
                </button>
                <div class="min-w-0 flex-1">
                    @isset($header)
                        <h1 class="truncate font-display text-lg font-semibold text-slate-900">{{ $header }}</h1>
                    @endisset
                </div>
                @isset($actions)
                    <div class="flex flex-none items-center gap-2">{{ $actions }}</div>
                @endisset
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-6xl space-y-6">
                    @if (auth()->user()->isContributor() && app(\App\Services\Maintenance::class)->isActive())
                        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <x-icon name="wrench" class="h-5 w-5 flex-none" />
                            <p>Le site est en maintenance : les pages publiques sont inaccessibles, mais vous pouvez continuer à rédiger vos articles.</p>
                        </div>
                    @endif
                    <x-flash />
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
