<x-public-layout :page="$page" :title="$project->title" :description="'Projet en maintenance.'">
    @push('head')<meta name="robots" content="noindex">@endpush
    <section class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
        <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900">{{ $project->title }}</h1>
        <p class="mt-3 text-slate-600">Ce projet est temporairement en maintenance. Revenez un peu plus tard.</p>
        <a href="{{ route('projects.index') }}" class="btn-secondary mt-6 inline-flex">Voir les autres projets</a>
    </section>
</x-public-layout>
