<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if ($hobbies->isEmpty())
            <x-empty-state icon="heart" message="Aucun loisir n'a encore été ajouté." />
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($hobbies as $hobby)
                    <article class="card overflow-hidden">
                        @if ($hobby->imageUrl())
                            <img src="{{ $hobby->imageUrl() }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover">
                        @endif
                        <div class="p-6">
                            <div class="flex items-center gap-3">
                                @unless ($hobby->imageUrl())
                                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-primary-50 text-primary-600"><x-icon name="heart" /></span>
                                @endunless
                                <h2 class="font-display text-lg font-semibold text-slate-900">{{ $hobby->name }}</h2>
                            </div>
                            @if ($hobby->description)
                                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $hobby->description }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-public-layout>
