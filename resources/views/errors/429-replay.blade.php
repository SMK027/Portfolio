@extends('errors.layout')
@section('code', '✓')
@section('title', 'Demande débloquée')
@section('message', 'Validation acceptée : envoi de votre demande…')

@section('extra')
    {{-- Rejeu de la requête bloquée, avec le jeton à usage unique --}}
    <form method="POST" action="{{ $action }}" id="throttle-replay" class="mt-6">
        @csrf
        @if ($method !== 'POST') @method($method) @endif
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <noscript><button class="btn-primary">Continuer</button></noscript>
    </form>
    <script>setTimeout(() => document.getElementById('throttle-replay').submit(), 50);</script>
@endsection
