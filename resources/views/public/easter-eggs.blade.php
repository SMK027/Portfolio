@push('head')<meta name="robots" content="noindex, nofollow, noarchive">@endpush
<x-public-layout :title="$settings['title']" description="">
    <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6" data-easter-eggs data-just-unlocked="{{ $justUnlocked }}">
        @if ($preview)
            <p class="mb-6 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-900">
                Aperçu administrateur : les conditions d'affichage s'appliquent, mais les combinaisons des éléments ne peuvent être testées qu'après un vrai déclenchement.
            </p>
        @endif

        <header class="mb-10 text-center">
            <x-icon name="sparkles" class="mx-auto h-10 w-10 text-primary-600" />
            <h1 class="mt-3 font-display text-3xl font-bold text-slate-900">{{ $settings['title'] }}</h1>
            @if ($settings['intro'])
                <p class="mx-auto mt-3 max-w-2xl whitespace-pre-line text-slate-700">{{ $settings['intro'] }}</p>
            @endif
        </header>

        {{-- Mobile : le clavier virtuel n'envoie pas de touches fiables, les combinaisons se saisissent ici --}}
        @if ($eggs->contains(fn ($egg) => ! in_array($egg->id, $unlocked, true)))
            <form data-egg-input hidden class="mx-auto mb-8 max-w-md space-y-2" autocomplete="off">
                <label for="egg-combination" class="form-label text-center">Une combinaison à tester ?</label>
                <div class="flex gap-2">
                    <input id="egg-combination" type="text" class="form-input flex-1" autocapitalize="none" autocorrect="off" spellcheck="false"
                           enterkeyhint="go" maxlength="255" placeholder="Un mot, ou des touches séparées par des espaces">
                    <button class="btn-primary"><x-icon name="lock" class="h-4 w-4" /> Essayer</button>
                </div>
                <p data-egg-input-error hidden class="form-error text-center">Ce n'est pas la bonne combinaison.</p>
            </form>
        @endif

        @if ($eggs->isEmpty())
            <x-empty-state icon="sparkles" message="Rien à voir ici… pour l'instant." />
        @else
            <div class="grid gap-6 sm:grid-cols-2">
                @foreach ($eggs as $egg)
                    @if (! in_array($egg->id, $unlocked, true))
                        {{-- Verrouillé : seule l'empreinte de la combinaison est transmise --}}
                        <article class="card flex flex-col items-center justify-center gap-3 border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center"
                                 data-egg-lock="{{ json_encode(['url' => route('easter-eggs.unlock', $egg), ...\App\Services\EasterEggs::fingerprint(explode(' ', $egg->unlock_sequence))]) }}">
                            <x-icon name="lock" class="h-8 w-8 text-slate-500" />
                            <p class="font-display text-lg font-semibold text-slate-700">???</p>
                            @if ($egg->hint)<p class="text-sm italic text-slate-600">Indice : {{ $egg->hint }}</p>@endif
                        </article>
                    @else
                        <article id="egg-{{ $egg->id }}" class="card space-y-4 p-6" @if ($egg->type === 'effect') data-egg-effect="{{ $egg->effect }}" data-egg-id="{{ $egg->id }}" @endif>
                            <h2 class="flex items-center gap-2 font-display text-lg font-semibold text-slate-900">
                                <x-icon :name="$egg->icon()" class="h-5 w-5 text-primary-600" /> {{ $egg->title }}
                            </h2>
                            @if ($egg->type === 'image' && $egg->images->isNotEmpty())
                                {{-- Clic : plein écran ; flèches, clavier et balayage pour passer d'une image à l'autre --}}
                                <x-carousel :images="$egg->images" :title="$egg->title" :label="'Images — '.$egg->title" />
                            @endif
                            @if ($egg->type === 'code')
                                {{-- Code isolé : origine opaque, aucun accès au site ni à la session --}}
                                <div class="space-y-2">
                                    <iframe src="{{ route('easter-eggs.code', $egg) }}" data-egg-frame="{{ $egg->id }}" title="{{ $egg->title }}"
                                            sandbox="allow-scripts allow-pointer-lock allow-modals" allow="fullscreen" referrerpolicy="no-referrer" loading="lazy"
                                            class="block h-64 w-full rounded-lg border border-slate-200 bg-white"></iframe>
                                    <div class="text-right">
                                        <button type="button" class="btn-ghost btn-sm" data-egg-fullscreen="{{ $egg->id }}"><x-icon name="arrows-expand" class="h-4 w-4" /> Plein écran</button>
                                    </div>
                                </div>
                            @endif
                            @if ($egg->body)
                                <div class="editor-content">{!! \Illuminate\Support\Str::markdown($egg->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
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
