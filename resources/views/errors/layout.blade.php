<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-4 font-sans antialiased">
    <div class="text-center">
        <p class="font-display text-7xl font-bold text-primary-600">@yield('code')</p>
        <h1 class="mt-4 font-display text-2xl font-semibold text-slate-900">@yield('title')</h1>
        <p class="mt-2 text-slate-500">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn-primary mt-8">Retour à l'accueil</a>
    </div>
</body>
</html>
