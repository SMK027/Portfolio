<x-public-layout :page="$page" :title="$page->title" description="Page en maintenance.">
    <section class="mx-auto max-w-xl px-4 py-16 text-center sm:px-6">
        <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900">{{ $page->title }}</h1>
        <p class="mt-3 text-slate-600">Cette page est temporairement en maintenance. Revenez un peu plus tard.</p>
        <a href="{{ url('/') }}" class="btn-secondary mt-6 inline-flex">Retour à l'accueil</a>
    </section>
</x-public-layout>
