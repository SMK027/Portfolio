<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $egg->title }}</title>
{{--
    Document isolé (origine opaque, voir EasterEggController::code). Le pont ci-dessous transmet
    la hauteur du contenu à la page et expose easterEgg.effect('confetti' | 'matrix' | 'barrel-roll' | 'invert').
--}}
<script>
(() => {
    const send = (data) => parent.postMessage({ easterEgg: {{ $egg->id }}, ...data }, '*');
    window.easterEgg = { effect: (name) => send({ effect: String(name) }) };
    const report = () => send({ height: Math.ceil(document.documentElement.getBoundingClientRect().height) });
    addEventListener('DOMContentLoaded', () => new ResizeObserver(report).observe(document.documentElement));
    addEventListener('load', report);
})();
</script>
<style>html { height: auto; } body { margin: 0; font-family: system-ui, sans-serif; }</style>
@if ($egg->code_css)<style>{!! $egg->code_css !!}</style>@endif
</head>
<body>
{!! $egg->code_html !!}
@if ($egg->code_js)<script>{!! $egg->code_js !!}</script>@endif
</body>
</html>
