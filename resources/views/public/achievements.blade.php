@php $categories = \App\Achievements\Triggers::CATEGORIES; $percent = $total ? round($unlocked / $total * 100) : 0; @endphp
<x-public-layout :title="$settings['title']">
    <x-page-header :title="$settings['title']" :intro="$settings['intro']">
        <div class="mt-8 max-w-md">
            <div class="flex items-baseline justify-between text-sm text-slate-300">
                <span><strong class="font-display text-2xl text-white">{{ $unlocked }}</strong> / {{ $total }} débloqués</span>
                <span>{{ $percent }} %</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-valuenow="{{ $unlocked }}" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-label="Succès débloqués">
                <div class="h-full rounded-full bg-gradient-to-r from-primary-400 to-accent-400" style="width: {{ $percent }}%"></div>
            </div>
        </div>
    </x-page-header>

    <div class="mx-auto max-w-6xl space-y-12 px-4 py-12 sm:px-6">
        @foreach ($categories as $key => [$label, $icon])
            @continue(! isset($groups[$key]))
            <section aria-labelledby="succes-{{ $key }}">
                <h2 id="succes-{{ $key }}" class="flex items-center gap-2 font-display text-xl font-semibold text-slate-900">
                    <x-icon :name="$icon" class="h-5 w-5 text-primary-600" /> {{ $label }}
                    <span class="text-sm font-normal text-slate-400">{{ $groups[$key]->whereNotNull('unlocked_at')->count() }}/{{ $groups[$key]->count() }}</span>
                </h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($groups[$key] as $item)
                        @php
                            $a = $item['achievement'];
                            $done = (bool) $item['unlocked_at'];
                            $hidden = $a->is_secret && ! $done;
                        @endphp
                        <article @class(['card flex gap-4 p-5', 'opacity-70' => ! $done])>
                            <span @class([
                                'flex h-12 w-12 flex-none items-center justify-center rounded-xl',
                                'bg-gradient-to-br from-amber-300 to-amber-500 text-white shadow' => $done,
                                'bg-slate-100 text-slate-400' => ! $done,
                            ])>
                                <x-icon :name="$hidden ? 'lock' : $a->icon" class="h-6 w-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold text-slate-900">{{ $hidden ? 'Succès secret' : $a->title }}</h3>
                                <p class="mt-0.5 text-sm text-slate-600">{{ $hidden ? 'Continuez à explorer pour le découvrir…' : $a->description }}</p>
                                @if ($done)
                                    <p class="mt-2 text-xs font-medium text-amber-600">Débloqué le {{ $item['unlocked_at']->translatedFormat('j F Y') }}</p>
                                @elseif (! $hidden && $a->threshold > 1)
                                    <div class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full bg-primary-500" style="width: {{ round($item['current'] / $a->threshold * 100) }}%"></div>
                                        </div>
                                        {{ $item['current'] }}/{{ $a->threshold }}
                                    </div>
                                @endif
                                <p class="mt-1 text-xs text-slate-400">{{ $item['rarity'] }} % des visiteurs</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if (! $total)
            <x-empty-state icon="trophy" message="Aucun succès n'est disponible pour l'instant." />
        @endif
    </div>
</x-public-layout>
