<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b1020">
    <title>Terminal — {{ $data['name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .terminal { font-family: ui-monospace, SFMono-Regular, 'JetBrains Mono', Menlo, Consolas, monospace; }
        .terminal .t-line { white-space: pre-wrap; word-break: break-word; min-height: 1.5em; }
        .terminal .t-prompt { color: #4ade80; }
        .terminal .t-accent { color: #a5b4fc; font-weight: 600; }
        .terminal .t-dim { color: #64748b; }
        .terminal .t-dir { color: #60a5fa; font-weight: 600; }
        .terminal .t-link { color: #67e8f9; text-decoration: underline; text-underline-offset: 2px; }
        .terminal .t-link:hover { color: #fff; }
        .terminal input { caret-color: #4ade80; }
    </style>
</head>
<body class="h-full bg-[#0b1020] text-slate-200">
    <main class="terminal flex h-full flex-col" data-terminal>
        <script type="application/json" data-terminal-data>@json($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
        <header class="flex flex-none items-center justify-between gap-3 border-b border-white/10 bg-white/5 px-4 py-2 text-xs text-slate-400">
            <div class="flex items-center gap-1.5" aria-hidden="true">
                <span class="h-3 w-3 rounded-full bg-red-500/80"></span><span class="h-3 w-3 rounded-full bg-amber-400/80"></span><span class="h-3 w-3 rounded-full bg-emerald-500/80"></span>
            </div>
            <h1 class="truncate">{{ $data['user'] }}@portfolio — terminal</h1>
            <a href="{{ $data['home'] }}" class="rounded px-2 py-1 hover:bg-white/10 hover:text-white">Quitter</a>
        </header>
        <div class="flex-1 overflow-y-auto px-4 py-4 text-sm leading-relaxed sm:px-6">
            <noscript><p>Le terminal nécessite JavaScript. <a href="{{ $data['home'] }}" class="underline">Revenir au site</a>.</p></noscript>
            <div data-terminal-output aria-live="polite"></div>
            <label class="flex items-baseline gap-2">
                <span data-terminal-prompt class="t-prompt flex-none"></span>
                <span class="sr-only">Commande</span>
                <input data-terminal-input type="text" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="send"
                       class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-100 focus:outline-none focus:ring-0">
            </label>
        </div>
    </main>
</body>
</html>
