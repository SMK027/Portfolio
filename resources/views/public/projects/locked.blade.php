<x-public-layout :page="$page" :title="$project->title" :description="'Projet protégé par mot de passe.'">
    @push('head')<meta name="robots" content="noindex">@endpush
    <section class="mx-auto max-w-md px-4 py-16 sm:px-6">
        <a href="{{ route('projects.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-primary-600">
            <x-icon name="arrow-left" class="h-4 w-4" /> {{ $page->title }}
        </a>
        <div class="card mt-6 p-6">
            <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900">{{ $project->title }}</h1>
            <p class="mt-2 text-sm text-slate-600">Ce projet est protégé. Saisissez le mot de passe pour le consulter.</p>
            <form method="POST" action="{{ route('projects.unlock', $project) }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="project-password" class="form-label">Mot de passe</label>
                    <input id="project-password" type="password" name="password" class="form-input" required autofocus autocomplete="current-password">
                    @error('password')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn-primary w-full">Accéder au projet</button>
            </form>
        </div>
    </section>
</x-public-layout>
