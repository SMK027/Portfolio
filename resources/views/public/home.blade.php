@php
    $canSee = fn (string $key) => \App\Models\Page::isKeyAccessibleBy($key, auth()->user());
    $hasAbout = ! empty($profile->about['blocks']);
@endphp
<x-public-layout :page="$page" :image="$profile->photoUrl()">
    @push('head')
        <script type="application/ld+json">{!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            'name'     => $profile->fullName(),
            'jobTitle' => $profile->headline,
            'url'      => url('/'),
            'image'    => $profile->photoUrl() ? url($profile->photoUrl()) : null,
            'address'  => $profile->location ? ['@type' => 'PostalAddress', 'addressLocality' => $profile->location] : null,
            'sameAs'   => $profile->socialLinks()->pluck('url')->values()->all() ?: null,
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endpush
    {{-- Présentation --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-primary-900">
        <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-primary-500/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-40 left-10 h-96 w-96 rounded-full bg-accent-500/15 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 sm:py-24 md:grid-cols-[1fr_auto]">
            <div class="order-2 md:order-1">
                {{-- Accroche en avant ; le nom reste le titre de la page (SEO) --}}
                @if ($profile->headline)
                    <h1 class="text-sm font-semibold uppercase tracking-widest text-primary-300">Bonjour, je suis {{ $profile->fullName() }}</h1>
                    <p class="mt-3 font-display text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">{{ $profile->headline }}</p>
                @else
                    <p class="text-sm font-semibold uppercase tracking-widest text-primary-300">Bonjour, je suis</p>
                    <h1 class="mt-3 font-display text-4xl font-bold tracking-tight text-white sm:text-6xl">{{ $profile->fullName() }}</h1>
                @endif

                {{-- « À propos » : texte courant, replié s'il est long --}}
                @if ($hasAbout)
                    <div class="mt-6 max-w-2xl" x-data="{ open: false, long: false }" x-init="long = $refs.about.scrollHeight > $refs.about.clientHeight + 4">
                        <div x-ref="about" class="editor-content hero-about overflow-hidden"
                             :class="{ 'max-h-36': ! open, 'hero-about-faded': long && ! open }">@editorjs($profile->about)</div>
                        <button type="button" x-show="long" x-cloak @click="open = ! open" class="mt-2 text-sm font-semibold text-primary-300 hover:text-primary-200"
                                x-text="open ? 'Réduire' : 'Lire la suite'"></button>
                    </div>
                @endif

                <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-400">
                    @if ($profile->location)
                        <li class="inline-flex items-center gap-1.5"><x-icon name="map-pin" class="h-4 w-4" /> {{ $profile->location }}</li>
                    @endif
                    @if ($profile->email && $canSee('contact'))
                        <li class="inline-flex items-center gap-1.5"><x-icon name="mail" class="h-4 w-4" /> {{ $profile->email }}</li>
                    @endif
                </ul>

                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($canSee('projets'))
                        <a href="{{ route('projects.index') }}" class="btn-primary px-5 py-3">Voir mes projets <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    @endif
                    @if ($canSee('contact'))
                        <a href="{{ route('contact.show') }}" class="btn border border-white/20 bg-white/10 px-5 py-3 text-white hover:bg-white/20">Me contacter</a>
                    @endif
                    @if ($profile->cvUrl())
                        <a href="{{ $profile->cvUrl() }}" target="_blank" rel="noopener" class="btn px-5 py-3 text-slate-200 hover:bg-white/10"><x-icon name="download" class="h-4 w-4" /> Mon CV</a>
                    @endif
                </div>

                <div class="mt-8 flex gap-2">
                    <x-social-links :profile="$profile" icon-class="h-6 w-6" link-class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white" />
                </div>
            </div>

            <div class="order-1 flex justify-center md:order-2">
                <div class="relative">
                    <div class="absolute -inset-3 rounded-full bg-gradient-to-br from-primary-400 to-accent-400 opacity-60 blur-lg"></div>
                    @if ($profile->photoUrl())
                        <img src="{{ $profile->photoUrl() }}" alt="Photo de {{ $profile->fullName() }}" fetchpriority="high" decoding="async" class="relative h-44 w-44 rounded-full border-4 border-white/20 object-cover sm:h-64 sm:w-64">
                    @else
                        <div class="relative flex h-44 w-44 items-center justify-center rounded-full border-4 border-white/20 bg-slate-800 font-display text-6xl font-bold text-white sm:h-64 sm:w-64">{{ $profile->initials() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Accès rapides au parcours --}}
    @php
        $shortcuts = collect([
            ['formations', 'formations', 'academic-cap'],
            ['experiences', 'experiences', 'briefcase'],
            ['diplomes', 'diplomes', 'diploma'],
            ['certifications', 'certifications', 'badge'],
            ['competences', 'competences', 'sparkles'],
        ])->filter(fn ($s) => $canSee($s[0]));
    @endphp
    @if ($shortcuts->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 sm:px-6">
            <div @class(['grid grid-cols-2 gap-4', 'md:grid-cols-4' => $shortcuts->count() % 4 === 0, 'md:grid-cols-3' => $shortcuts->count() % 4 !== 0, 'lg:grid-cols-5' => $shortcuts->count() === 5])>
                @foreach ($shortcuts as [$key, $route, $icon])
                    @php $p = \App\Models\Page::findByKey($key); @endphp
                    <a href="{{ route($route) }}" class="card group flex flex-col gap-3 p-5 transition hover:border-primary-200 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 transition group-hover:bg-primary-600 group-hover:text-white"><x-icon :name="$icon" /></span>
                        <span class="font-display font-semibold text-slate-900">{{ $p->title }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($skills->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6">
            <div class="mb-6 flex items-end justify-between gap-4">
                <h2 class="font-display text-2xl font-bold text-slate-900">Compétences</h2>
                <a href="{{ route('competences') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-700">Tout voir <x-icon name="arrow-right" class="h-4 w-4" /></a>
            </div>
            <div class="card divide-y divide-slate-100">
                @foreach ($skills as $category => $items)
                    <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center">
                        <h3 class="w-48 flex-none text-sm font-semibold text-slate-500">{{ $category }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($items as $skill)
                                <span class="badge-slate px-3 py-1 text-sm">{{ $skill->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($projects->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6">
            <div class="mb-6 flex items-end justify-between gap-4">
                <h2 class="font-display text-2xl font-bold text-slate-900">Projets récents</h2>
                <a href="{{ route('projects.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-700">Tous les projets <x-icon name="arrow-right" class="h-4 w-4" /></a>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    <x-project-card :project="$project" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($articles->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6">
            <div class="mb-6 flex items-end justify-between gap-4">
                <h2 class="font-display text-2xl font-bold text-slate-900">Dernières veilles</h2>
                <a href="{{ route('articles.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-700">Toute la veille <x-icon name="arrow-right" class="h-4 w-4" /></a>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-article-card :article="$article" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($canSee('contact'))
        <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6">
            <div class="flex flex-col items-start justify-between gap-6 rounded-3xl bg-gradient-to-r from-primary-600 to-accent-600 p-8 text-white sm:flex-row sm:items-center sm:p-12">
                <div>
                    <h2 class="font-display text-2xl font-bold sm:text-3xl">Travaillons ensemble</h2>
                    <p class="mt-2 text-primary-100">Une question, un projet, une opportunité ? Je vous réponds rapidement.</p>
                </div>
                <a href="{{ route('contact.show') }}" class="btn flex-none bg-white px-6 py-3 text-primary-700 hover:bg-primary-50">Me contacter <x-icon name="arrow-right" class="h-4 w-4" /></a>
            </div>
        </section>
    @endif
</x-public-layout>
