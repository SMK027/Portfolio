<x-guest-layout>
    <h1 class="mb-1 font-display text-xl font-bold text-slate-900">Connexion d'un bot</h1>
    <p class="mb-6 text-sm text-slate-500">Saisissez le code d'application fourni par un super-administrateur.</p>

    <form method="POST" action="{{ route('login.bot') }}" class="space-y-4">
        @csrf
        <div>
            <label for="code" class="form-label">Code d'application</label>
            <input id="code" name="code" type="password" required autofocus autocomplete="off" class="form-input font-mono">
            @error('code')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full">Se connecter</button>
    </form>

    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="text-slate-500 underline hover:text-slate-700">Connexion avec un mot de passe</a></p>
</x-guest-layout>
