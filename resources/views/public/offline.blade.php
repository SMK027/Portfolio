<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Hors connexion — {{ $profile->fullName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-50 px-4 py-12 font-sans text-slate-800 antialiased" data-offline>
    <main class="card w-full max-w-lg space-y-5 p-6 text-center sm:p-8">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-50 text-primary-600"><x-icon name="globe" class="h-6 w-6" /></span>
        <div>
            <h1 class="font-display text-xl font-bold text-slate-900">Vous êtes hors connexion</h1>
            <p class="mt-2 text-sm text-slate-600">Cette page n'a pas encore été consultée sur cet appareil. Les pages déjà lues restent disponibles :</p>
        </div>
        <div data-offline-pages class="hidden text-left">
            <ul data-offline-list class="max-h-80 space-y-0.5 overflow-y-auto rounded-xl border border-slate-200 p-1"></ul>
        </div>
        <button type="button" onclick="location.reload()" class="btn-primary">Réessayer</button>
    </main>
</body>
</html>
