<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-6xl space-y-14 px-4 py-12 sm:px-6">
        @if ($themes->isNotEmpty())
            <section>
                <h2 class="mb-5 font-display text-2xl font-bold text-slate-900">Par thème</h2>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($themes as $theme)
                        <x-theme-card :theme="$theme" />
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <h2 class="mb-5 font-display text-2xl font-bold text-slate-900">Tous les projets</h2>
            @if ($projects->isEmpty())
                <x-empty-state icon="folder" message="Aucun projet n'a encore été publié." />
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($projects as $project)
                        <x-project-card :project="$project" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $projects->links() }}</div>
            @endif
        </section>
    </div>
</x-public-layout>
