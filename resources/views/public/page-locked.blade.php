<x-public-layout :page="$page" :title="$page->title" description="Page protégée par mot de passe.">
    <section class="mx-auto max-w-md px-4 py-16 sm:px-6">
        <div class="card p-6">
            <h1 class="flex items-center gap-2 font-display text-2xl font-bold tracking-tight text-slate-900"><x-icon name="lock" class="h-5 w-5 text-slate-400" /> {{ $page->title }}</h1>
            <p class="mt-2 text-sm text-slate-600">Cette page est protégée. Saisissez le mot de passe pour la consulter.</p>
            <form method="POST" action="{{ route('pages.unlock', $page->key) }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="page-password" class="form-label">Mot de passe</label>
                    <input id="page-password" type="password" name="password" class="form-input" required autofocus autocomplete="current-password">
                    @error('password')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary w-full">Accéder à la page</button>
            </form>
        </div>
    </section>
</x-public-layout>
