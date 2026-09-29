<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if ($groups->isEmpty())
            <x-empty-state icon="sparkles" message="Aucune compétence n'a encore été ajoutée." />
        @else
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach ($groups as $category => $skills)
                    <section class="card p-6">
                        <h2 class="mb-4 font-display text-lg font-semibold text-slate-900">{{ $category }}</h2>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($skills as $skill)
                                <li class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="font-medium text-slate-800">{{ $skill->name }}</span>
                                        <x-skill-level :level="$skill->level" />
                                    </div>
                                    @if ($skill->description)
                                        <p class="text-sm text-slate-500">{{ $skill->description }}</p>
                                    @endif
                                    @if ($skill->projects_count)
                                        <p class="text-xs text-slate-400">Mise en œuvre dans {{ $skill->projects_count }} {{ \Illuminate\Support\Str::plural('projet', $skill->projects_count) }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-public-layout>
