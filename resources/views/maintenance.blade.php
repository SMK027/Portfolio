<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance en cours — {{ $profile->fullName() }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600|space-grotesk:600,700&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600|space-grotesk:600,700&display=swap"></noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
    <main class="relative flex min-h-full items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-primary-900 px-4 py-16">
        <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-primary-500/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-40 left-10 h-96 w-96 rounded-full bg-accent-500/15 blur-3xl"></div>

        <div class="relative w-full max-w-xl text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-primary-200 ring-1 ring-white/20">
                <x-icon name="wrench" class="h-8 w-8" />
            </span>
            <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-primary-300">{{ $profile->fullName() }}</p>
            <h1 class="mt-2 font-display text-3xl font-bold text-white sm:text-4xl">Site en maintenance</h1>

            @if ($reason)
                <p class="mx-auto mt-5 max-w-lg whitespace-pre-line text-lg text-slate-300">{{ $reason }}</p>
            @else
                <p class="mx-auto mt-5 max-w-lg text-lg text-slate-300">Le site est temporairement indisponible. Merci de revenir un peu plus tard.</p>
            @endif

            @if ($endsAt)
                <div class="mt-8 inline-flex flex-wrap items-center justify-center gap-x-2 gap-y-1 rounded-2xl bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/15"
                     x-data="{ ends: {{ $endsAt->getTimestampMs() }}, left: '' }"
                     x-init="const tick = () => {
                                 const s = Math.max(0, Math.round((ends - Date.now()) / 1000));
                                 if (s === 0) { window.location.reload(); return; }
                                 const h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
                                 left = (h ? h + ' h ' : '') + (h || m ? m + ' min ' : '') + sec + ' s';
                             };
                             tick(); setInterval(tick, 1000)">
                    <x-icon name="clock" class="h-4 w-4" />
                    <span>Retour prévu le {{ $endsAt->translatedFormat('j F Y à H:i') }}</span>
                    <span x-show="left" x-cloak class="whitespace-nowrap text-primary-200">· dans <span x-text="left"></span></span>
                </div>
            @endif

            @if ($profile->email)
                <p class="mt-10 text-sm text-slate-400">
                    Besoin de me joindre ? <a href="mailto:{{ $profile->email }}" class="font-medium text-white underline decoration-white/30 hover:decoration-white">{{ $profile->email }}</a>
                </p>
            @endif
        </div>

        <a href="{{ route('login') }}" class="absolute bottom-4 right-4 text-xs text-slate-500 hover:text-slate-300">Espace administrateur</a>
    </main>
</body>
</html>
