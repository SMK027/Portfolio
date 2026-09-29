<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if ($diplomas->isEmpty())
            <x-empty-state icon="diploma" message="Aucun diplôme n'a encore été ajouté." />
        @else
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($diplomas as $diploma)
                    <article class="card flex gap-4 p-5 sm:p-6">
                        <span class="flex h-12 w-12 flex-none items-center justify-center rounded-xl bg-primary-50 text-primary-600"><x-icon name="diploma" class="h-6 w-6" /></span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="font-medium text-slate-500">{{ $diploma->obtained_at->translatedFormat('F Y') }}</span>
                                @if ($diploma->level)<span class="badge-primary">{{ $diploma->level }}</span>@endif
                                @if ($diploma->mention)<span class="badge-amber">Mention {{ $diploma->mention }}</span>@endif
                            </div>
                            <h2 class="mt-1 font-display text-lg font-semibold text-slate-900">{{ $diploma->title }}</h2>
                            <p class="text-sm text-slate-600">{{ $diploma->institution }}</p>
                            @if ($diploma->description)
                                <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $diploma->description }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-public-layout>
