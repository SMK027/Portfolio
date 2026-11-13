<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>File d'attente — {{ $profile->fullName() }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600|space-grotesk:600,700&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600|space-grotesk:600,700&display=swap">
        <meta http-equiv="refresh" content="15">
    </noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
    <main class="relative flex min-h-full items-center justify-center overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-primary-900 px-4 py-16">
        <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-primary-500/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-40 left-10 h-96 w-96 rounded-full bg-accent-500/15 blur-3xl"></div>

        <div class="relative w-full max-w-xl text-center"
             x-data="{ position: {{ $position }}, minutes: {{ $minutes }} }"
             x-init="const poll = async () => {
                         try {
                             const r = await fetch(@js(route('waiting-room.status')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                             if (r.ok) {
                                 const d = await r.json();
                                 if (d.admitted || d.expired) { window.location.reload(); return; }
                                 position = d.position; minutes = d.minutes;
                             }
                         } catch (e) {}
                         setTimeout(poll, 5000 + Math.random() * 3000);
                     };
                     setTimeout(poll, 5000)">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-primary-200 ring-1 ring-white/20">
                <x-icon name="users" class="h-8 w-8" />
            </span>
            <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-primary-300">{{ $profile->fullName() }}</p>
            <h1 class="mt-2 font-display text-3xl font-bold text-white sm:text-4xl">Vous êtes dans la file d'attente</h1>

            @if ($message)
                <p class="mx-auto mt-5 max-w-lg whitespace-pre-line text-lg text-slate-300">{{ $message }}</p>
            @else
                <p class="mx-auto mt-5 max-w-lg text-lg text-slate-300">Le site connaît une forte affluence. Gardez cette page ouverte : vous entrerez automatiquement dès qu'une place se libère.</p>
            @endif

            <div class="mx-auto mt-8 grid max-w-sm grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                    <p class="text-xs uppercase tracking-wider text-slate-400">Votre position</p>
                    <p class="mt-1 font-display text-3xl font-bold text-white" x-text="position">{{ $position }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                    <p class="text-xs uppercase tracking-wider text-slate-400">Attente estimée</p>
                    <p class="mt-1 font-display text-3xl font-bold text-white">≈ <span x-text="minutes">{{ $minutes }}</span> min</p>
                </div>
            </div>

            <p class="mt-6 inline-flex items-center gap-2 text-sm text-slate-400">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
                Mise à jour automatique — ne rechargez pas la page, vous garderiez votre place mais sans gagner de temps.
            </p>
        </div>
    </main>
</body>
</html>
