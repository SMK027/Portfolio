<x-public-layout :page="$page" :title="$theme->name" :description="$theme->description" :image="$theme->backgroundUrl()">
    <x-page-header :title="$theme->name" :intro="$theme->description" :background="$theme->backgroundUrl()" eyebrow="Thème">
    </x-page-header>

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <nav class="mb-8 flex flex-wrap items-center gap-2" aria-label="Thèmes">
            <a href="{{ route('projects.index') }}" class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1 text-sm text-slate-600 hover:border-primary-300 hover:text-primary-700">
                <x-icon name="arrow-left" class="h-4 w-4" /> Tous les projets
            </a>
            @foreach ($themes as $item)
                <a href="{{ route('projects.theme', $item) }}" @class([
                    'rounded-full border px-3 py-1 text-sm transition',
                    'border-primary-500 bg-primary-50 font-medium text-primary-700' => $item->is($theme),
                    'border-slate-300 bg-white text-slate-600 hover:border-primary-300 hover:text-primary-700' => ! $item->is($theme),
                ]) @if ($item->is($theme)) aria-current="page" @endif>{{ $item->name }}</a>
            @endforeach
        </nav>

        @if ($projects->isEmpty())
            <x-empty-state icon="folder" message="Aucun projet dans ce thème pour le moment." />
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    <x-project-card :project="$project" />
                @endforeach
            </div>
            <div class="mt-8">{{ $projects->links() }}</div>
        @endif
    </div>
</x-public-layout>
