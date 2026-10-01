<x-public-layout :page="$page" :title="$project->title" :description="$project->excerpt(160)" :image="$project->thumbnailUrl()">
    <article>
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <a href="{{ route('projects.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-primary-600">
                    <x-icon name="arrow-left" class="h-4 w-4" /> {{ $page->title }}
                </a>
                <h1 class="mt-4 font-display text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ $project->title }}</h1>
                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" class="h-4 w-4" />
                        <time datetime="{{ $project->published_on->toDateString() }}">{{ $project->published_on->translatedFormat('j F Y') }}</time>
                    </span>
                    @foreach ($project->themes as $theme)
                        <a href="{{ route('projects.theme', $theme) }}" class="badge-primary hover:bg-primary-100">{{ $theme->name }}</a>
                    @endforeach
                </div>
            </div>
        </header>

        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-10 sm:px-6 lg:grid-cols-[1fr_300px]">
            <div class="min-w-0 space-y-10">
                @if ($project->images()->isNotEmpty())
                    <x-carousel :images="$project->images()" :title="$project->title" />
                @endif

                <section>
                    <h2 class="sr-only">Description</h2>
                    <div class="editor-content">@editorjs($project->description)</div>
                </section>

                @if ($project->documents()->isNotEmpty())
                    <section>
                        <h2 class="mb-4 font-display text-xl font-semibold text-slate-900">Documents</h2>
                        <x-file-list :documents="$project->documents()" />
                    </section>
                @endif
            </div>

            <aside class="space-y-6">
                @if ($project->links->isNotEmpty())
                    <section class="card p-5">
                        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Liens</h2>
                        <ul class="space-y-2">
                            @foreach ($project->links as $link)
                                <li>
                                    <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer"
                                       class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700">
                                        <x-icon :name="$link->isGithub() ? 'github' : 'link'" class="h-5 w-5 flex-none" />
                                        <span class="min-w-0 flex-1 truncate">{{ $link->displayLabel() }}</span>
                                        <x-icon name="external" class="h-4 w-4 flex-none opacity-50" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Dépôts GitHub : étoiles, langage, dernière activité (données en cache) --}}
                @foreach ($project->links->filter->isGithub() as $link)
                    @php $repo = app(\App\Services\GithubRepositories::class)->find($link->url); @endphp
                    @if ($repo)
                        <section class="card p-5">
                            <a href="{{ $repo['url'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 font-semibold text-slate-900 hover:text-primary-700">
                                <x-icon name="github" class="h-5 w-5 flex-none" /> <span class="min-w-0 truncate">{{ $repo['name'] }}</span>
                            </a>
                            @if ($repo['archived'])<span class="badge-amber mt-2">Archivé</span>@endif
                            @if ($repo['description'])<p class="mt-2 text-sm text-slate-600">{{ $repo['description'] }}</p>@endif
                            <dl class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                                <div class="flex items-center gap-1" title="Étoiles"><dt class="sr-only">Étoiles</dt><span aria-hidden="true">★</span><dd>{{ number_format($repo['stars'], 0, ',', ' ') }}</dd></div>
                                <div class="flex items-center gap-1" title="Forks"><dt class="sr-only">Forks</dt><span aria-hidden="true">⑂</span><dd>{{ number_format($repo['forks'], 0, ',', ' ') }}</dd></div>
                                @if ($repo['language'])<div class="flex items-center gap-1.5"><dt class="sr-only">Langage</dt><span class="h-2.5 w-2.5 rounded-full bg-primary-500" aria-hidden="true"></span><dd>{{ $repo['language'] }}</dd></div>@endif
                                @if ($repo['license'] && $repo['license'] !== 'NOASSERTION')<div><dt class="sr-only">Licence</dt><dd>{{ $repo['license'] }}</dd></div>@endif
                            </dl>
                            @if ($pushed = \App\Services\GithubRepositories::pushedAt($repo))
                                <p class="mt-2 text-xs text-slate-500">Dernière activité <time datetime="{{ $pushed->toIso8601String() }}">{{ $pushed->diffForHumans() }}</time></p>
                            @endif
                            @if ($repo['topics'])
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach ($repo['topics'] as $topic)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $topic }}</span>@endforeach
                                </div>
                            @endif
                        </section>
                    @endif
                @endforeach

                @if ($project->skills->isNotEmpty())
                    <section class="card p-5">
                        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Compétences mobilisées</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($project->skills as $skill)
                                @if ($linkSkills)
                                    <a href="{{ route('competences') }}" class="badge-slate px-3 py-1 text-sm hover:bg-slate-200">{{ $skill->name }}</a>
                                @else
                                    <span class="badge-slate px-3 py-1 text-sm">{{ $skill->name }}</span>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif

                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.projets.edit', $project) }}" class="btn-secondary w-full"><x-icon name="pencil" class="h-4 w-4" /> Modifier ce projet</a>
                    @endif
                @endauth
            </aside>
        </div>
    </article>
</x-public-layout>
