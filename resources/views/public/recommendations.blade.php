<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if ($recommendations->isEmpty())
            <x-empty-state icon="badge" message="Aucune recommandation n'a encore été publiée." />
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($recommendations as $recommendation)
                    <x-recommendation-card :recommendation="$recommendation" />
                @endforeach
            </div>
        @endif
    </div>
</x-public-layout>
