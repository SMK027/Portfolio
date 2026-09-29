{{-- Bandeaux d'annonces affichés sur toutes les pages publiques --}}
@props(['announcements'])
@php
    $styles = [
        'primary' => 'from-primary-600 to-accent-600 text-white',
        'success' => 'from-emerald-600 to-teal-600 text-white',
        'info'    => 'from-sky-600 to-blue-600 text-white',
        'warning' => 'from-amber-400 to-orange-400 text-amber-950',
    ];
@endphp
@if ($announcements->isNotEmpty())
    <div aria-label="Annonces" role="region">
        @foreach ($announcements as $announcement)
            <div x-data="{ show: true, key: @js($announcement->dismissKey()) }"
                 x-init="try { show = localStorage.getItem(key) !== '1' } catch (e) {}"
                 x-show="show" x-transition.opacity
                 class="bg-gradient-to-r {{ $styles[$announcement->style] ?? $styles['primary'] }}">
                <div class="mx-auto flex max-w-6xl items-start gap-3 px-4 py-3 sm:items-center sm:px-6">
                    <x-icon :name="$announcement->icon()" class="mt-0.5 h-5 w-5 flex-none opacity-90 sm:mt-0" />
                    <div class="min-w-0 flex-1 text-sm sm:flex sm:flex-wrap sm:items-center sm:gap-x-3">
                        <p class="font-semibold">{{ $announcement->title }}</p>
                        @if ($announcement->message)
                            <p class="opacity-90">{{ $announcement->message }}</p>
                        @endif
                        @if ($announcement->link_url)
                            <a href="{{ $announcement->link_url }}"
                               @unless (str_starts_with($announcement->link_url, url('/'))) target="_blank" rel="noopener" @endunless
                               class="mt-2 inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold backdrop-blur hover:bg-white/30 sm:mt-0">
                                {{ $announcement->link_label ?: 'En savoir plus' }} <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                            </a>
                        @endif
                    </div>
                    @if ($announcement->is_dismissible)
                        <button type="button" @click="show = false; try { localStorage.setItem(key, '1') } catch (e) {}"
                                class="flex-none rounded-lg p-1 opacity-75 hover:bg-white/20 hover:opacity-100" aria-label="Masquer cette annonce">
                            <x-icon name="x" class="h-4 w-4" />
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
