<x-app-layout>
    <x-slot name="title">Opération validée</x-slot>
    <x-slot name="header">Opération validée</x-slot>

    {{-- Rejeu de la requête en attente, avec le bypass à usage unique --}}
    <form method="POST" action="{{ $action }}" id="supervision-replay" class="card mx-auto max-w-lg space-y-3 p-6 text-center">
        @csrf
        @if ($method !== 'POST') @method($method) @endif
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <p class="text-sm text-slate-600">Validation acceptée : exécution de l'opération…</p>
        <noscript><button class="btn-primary">Continuer</button></noscript>
    </form>
    <script>setTimeout(() => document.getElementById('supervision-replay').submit(), 50);</script>
</x-app-layout>
