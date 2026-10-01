{{-- Palette de recherche : Ctrl+K, ⌘K ou « / » ; résultats au fil de la frappe --}}
<div x-data="searchPalette(@js(route('search')))" x-show="open" x-cloak
     @open-search.window="show()" @keydown.window="shortcut($event)" @keydown.escape.window="open = false"
     class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 px-4 pt-[12vh] backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Recherche">
    <div @click.outside="open = false" class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <form :action="url" method="GET" class="flex items-center gap-3 border-b border-slate-200 px-4">
            <x-icon name="search" class="h-5 w-5 flex-none text-slate-400" />
            <input x-ref="input" x-model="query" @input.debounce.200ms="fetchResults()" name="q" type="search" autocomplete="off"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="choose($event)"
                   placeholder="Rechercher un article, un projet, une compétence…" class="h-14 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 focus:ring-0" aria-label="Rechercher">
            <kbd class="hidden rounded border border-slate-200 px-1.5 text-xs text-slate-400 sm:block">Échap</kbd>
        </form>
        <ul x-show="results.length" class="max-h-[50vh] overflow-y-auto py-2" role="listbox">
            <template x-for="(result, i) in results" :key="result.url + i">
                <li role="option" :aria-selected="(i === active).toString()">
                    <a :href="result.url" @mouseenter="active = i" :class="i === active ? 'bg-primary-50' : ''" class="block px-4 py-2.5">
                        <span class="mr-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600" x-text="result.type"></span>
                        <span class="text-sm font-medium text-slate-900" x-text="result.title"></span>
                        <span class="mt-0.5 line-clamp-1 block text-xs text-slate-500" x-text="result.excerpt"></span>
                    </a>
                </li>
            </template>
        </ul>
        <p x-show="query.trim().length >= 2 && ! loading && ! results.length && searched" class="px-4 py-6 text-center text-sm text-slate-500">Aucun résultat pour « <span x-text="query"></span> ».</p>
        <div x-show="results.length" class="border-t border-slate-100 px-4 py-2 text-right">
            <a :href="url + '?q=' + encodeURIComponent(query)" class="text-xs font-semibold text-primary-600 hover:text-primary-700">Voir tous les résultats →</a>
        </div>
    </div>
</div>
