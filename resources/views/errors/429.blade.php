@extends('errors.layout')
@section('code', '429')
@section('title', "Trop de requêtes")
@section('message', "Vous avez effectué trop de tentatives. Patientez quelques minutes.")

@if ($supervision ?? false)
    @section('extra')
        {{-- Bypass superviseur : la requête bloquée est rejouée une fois après validation --}}
        <form method="POST" action="{{ route('throttle-supervision.store') }}" class="card mx-auto mt-8 max-w-sm space-y-4 p-6 text-left">
            @csrf
            <p class="text-sm text-slate-600">Un superviseur peut débloquer votre demande immédiatement.</p>
            <div>
                <label for="sv-username" class="form-label">Identifiant superviseur</label>
                <input id="sv-username" name="username" value="{{ old('username') }}" required maxlength="50" autocomplete="off" class="form-input">
            </div>
            <div>
                <label for="sv-pin" class="form-label">Code PIN</label>
                <input id="sv-pin" name="pin" type="password" inputmode="numeric" required minlength="4" maxlength="8" autocomplete="off" class="form-input">
                @foreach ($errors->get('pin') + $errors->get('username') as $message)
                    <p class="form-error">{{ $message }}</p>
                @endforeach
            </div>
            <button type="submit" class="btn-primary w-full">Valider et continuer</button>
        </form>
    @endsection
@endif
