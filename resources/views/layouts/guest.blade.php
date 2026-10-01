<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ?? 'Connexion' }} — {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|space-grotesk:600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-primary-900 px-4 py-10">
            <a href="{{ url('/') }}" class="mb-6 font-display text-2xl font-bold text-white">Portfolio<span class="text-primary-400">.</span>admin</a>

            <div class="w-full max-w-md rounded-2xl bg-white px-6 py-8 shadow-xl sm:px-8">
                {{ $slot }}
            </div>

            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center gap-1 text-sm text-slate-300 hover:text-white">
                <x-icon name="arrow-left" class="h-4 w-4" /> Retour au site
            </a>
        </div>
    </body>
</html>
