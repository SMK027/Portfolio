@extends('errors.layout')
@section('code', (string) $status)
@section('title', $ban->banned_until ? 'Accès temporairement bloqué' : 'Accès bloqué')
@section('message')
    {{ $message }}
    @if ($ban->banned_until)
        <br>Réessayez après {{ $ban->banned_until->timezone(config('app.timezone'))->format('H:i') }} ({{ $ban->banned_until->diffForHumans() }}).
    @endif
@endsection
