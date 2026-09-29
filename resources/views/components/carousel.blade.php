{{-- Carrousel d'images avec miniatures et mode plein écran --}}
@props(['images', 'title' => ''])
@php $count = $images->count(); @endphp
<div x-data="carousel({ count: {{ $count }} })" @keydown.left="prev()" @keydown.right="next()" class="space-y-3" role="region" aria-roledescription="carrousel" aria-label="Images du projet">
    <div class="relative overflow-hidden rounded-2xl bg-slate-900" tabindex="0" @touchstart.passive="onTouchStart($event)" @touchend="onTouchEnd($event)">
        <div class="flex transition-transform duration-500 ease-out" :style="`transform: translateX(-${index * 100}%)`">
            @foreach ($images as $i => $image)
                <button type="button" class="block aspect-video w-full flex-none" @click="open({{ $i }})"
                        aria-label="Agrandir l'image {{ $i + 1 }} sur {{ $count }}" :aria-hidden="(index !== {{ $i }}).toString()" :tabindex="index === {{ $i }} ? 0 : -1">
                    <img src="{{ $image->url() }}" alt="{{ $title }} — image {{ $i + 1 }}" @if ($i > 0) loading="lazy" @endif class="h-full w-full object-contain">
                </button>
            @endforeach
        </div>

        @if ($count > 1)
            <button type="button" @click="prev()" class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 text-slate-800 shadow backdrop-blur hover:bg-white" aria-label="Image précédente">
                <x-icon name="chevron-left" class="h-5 w-5" />
            </button>
            <button type="button" @click="next()" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/80 p-2 text-slate-800 shadow backdrop-blur hover:bg-white" aria-label="Image suivante">
                <x-icon name="chevron-right" class="h-5 w-5" />
            </button>
            <span class="absolute bottom-3 right-3 rounded-full bg-slate-900/70 px-2.5 py-1 text-xs font-medium text-white" x-text="`${index + 1} / ${count}`"></span>
        @endif
        <button type="button" @click="open()" class="absolute right-3 top-3 rounded-full bg-slate-900/60 p-2 text-white hover:bg-slate-900/80" aria-label="Plein écran">
            <x-icon name="arrows-expand" class="h-4 w-4" />
        </button>
    </div>

    @if ($count > 1)
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach ($images as $i => $image)
                <button type="button" @click="go({{ $i }})"
                        :class="index === {{ $i }} ? 'ring-2 ring-primary-500 opacity-100' : 'opacity-60 hover:opacity-100'"
                        class="h-16 w-24 flex-none overflow-hidden rounded-lg transition" aria-label="Afficher l'image {{ $i + 1 }}">
                    <img src="{{ $image->url() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                </button>
            @endforeach
        </div>
    @endif

    {{-- Plein écran --}}
    <template x-teleport="body">
        <div x-show="fullscreen" x-cloak x-transition.opacity @keydown.escape.window="close()" @keydown.left.window="fullscreen && prev()" @keydown.right.window="fullscreen && next()"
             class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/95 p-4" role="dialog" aria-modal="true" aria-label="Visionneuse d'images"
             @touchstart.passive="onTouchStart($event)" @touchend="onTouchEnd($event)">
            @foreach ($images as $i => $image)
                <img x-show="index === {{ $i }}" src="{{ $image->url() }}" alt="{{ $title }} — image {{ $i + 1 }}" loading="lazy" class="max-h-full max-w-full object-contain">
            @endforeach
            <button type="button" @click="close()" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Fermer">
                <x-icon name="x" class="h-6 w-6" />
            </button>
            @if ($count > 1)
                <button type="button" @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20" aria-label="Image précédente"><x-icon name="chevron-left" class="h-6 w-6" /></button>
                <button type="button" @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20" aria-label="Image suivante"><x-icon name="chevron-right" class="h-6 w-6" /></button>
                <span class="absolute bottom-4 left-1/2 -translate-x-1/2 text-sm text-slate-300" x-text="`${index + 1} / ${count}`"></span>
            @endif
        </div>
    </template>
</div>
