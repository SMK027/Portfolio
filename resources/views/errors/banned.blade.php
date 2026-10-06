@extends('errors.layout')
@section('code', '403')
@section('title', 'Accès temporairement bloqué')
@section('message')
    {{ $message }}
    @if ($ban->banned_until)
        <br>Réessayez après {{ $ban->banned_until->timezone(config('app.timezone'))->format('H:i') }} ({{ $ban->banned_until->diffForHumans() }}).
    @endif
@endsection
