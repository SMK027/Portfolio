@push('head')<meta name="robots" content="noindex, nofollow, noarchive">@endpush
<x-public-layout :title="$settings['title']" description="">
    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6" data-easter-eggs data-just-unlocked="{{ $justUnlocked }}">
        @if ($preview)
            <p class="mb-6 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-900">
                Aperçu administrateur : les conditions d'affichage s'appliquent, mais les combinaisons des éléments ne peuvent être testées qu'après un vrai déclenchement.
            </p>
        @endif

        <header class="mb-10 text-center">
            <x-icon name="sparkles" class="mx-auto h-10 w-10 text-primary-500" />
            <h1 class="mt-3 font-display text-3xl font-bold text-slate-900 dark:text-white">{{ $settings['title'] }}</h1>
            @if ($settings['intro'])
                <p class="mx-auto mt-3 max-w-2xl whitespace-pre-line text-slate-600 dark:text-slate-300">{{ $settings['intro'] }}</p>
            @endif
        </header>

        @if ($eggs->isEmpty())
            <x-empty-state icon="sparkles" message="Rien à voir ici… pour l'instant." />
        @else
            <div class="grid gap-6 sm:grid-cols-2">
                @foreach ($eggs as $egg)
                    @if (! in_array($egg->id, $unlocked, true))
                        {{-- Verrouillé : seule l'empreinte de la combinaison est transmise --}}
                        <article class="card flex flex-col items-center justify-center gap-3 border-dashed p-6 text-center"
                                 data-egg-lock="{{ json_encode(['url' => route('easter-eggs.unlock', $egg), ...\App\Services\EasterEggs::fingerprint(explode(' ', $egg->unlock_sequence))]) }}">
                            <x-icon name="lock" class="h-8 w-8 text-slate-300" />
                            <p class="font-display font-semibold text-slate-400">???</p>
                            @if ($egg->hint)<p class="text-sm italic text-slate-500">Indice : {{ $egg->hint }}</p>@endif
                        </article>
                    @else
                        <article id="egg-{{ $egg->id }}" class="card space-y-4 p-6" @if ($egg->type === 'effect') data-egg-effect="{{ $egg->effect }}" data-egg-id="{{ $egg->id }}" @endif>
                            <h2 class="flex items-center gap-2 font-display text-lg font-semibold text-slate-900 dark:text-white">
                                <x-icon :name="$egg->icon()" class="h-5 w-5 text-primary-500" /> {{ $egg->title }}
                            </h2>
                            @if ($egg->type === 'image' && $egg->image_path)
                                <img src="{{ route('easter-eggs.image', $egg) }}" alt="{{ $egg->title }}" loading="lazy" class="w-full rounded-lg">
                            @endif
                            @if ($egg->body)
                                <div class="prose prose-slate max-w-none dark:prose-invert">{!! \Illuminate\Support\Str::markdown($egg->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                            @endif
                            @if ($egg->type === 'link' && $egg->link_url)
                                <a href="{{ $egg->link_url }}" target="_blank" rel="noopener noreferrer nofollow" class="btn-primary">
                                    {{ $egg->link_label ?: 'Ouvrir' }} <x-icon name="external" class="h-4 w-4" />
                                </a>
                            @endif
                            @if ($egg->type === 'effect')
                                <button type="button" class="btn-primary" data-egg-play><x-icon name="sparkles" class="h-4 w-4" /> Lancer</button>
                            @endif
                        </article>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-public-layout>
