{{-- Palette du panel (Ctrl+K / ⌘K) : accès rapide aux pages du menu, au clavier --}}
<div x-data="adminPalette" x-show="open" x-cloak
     @admin-palette.window="show()" @keydown.window="shortcut($event)" @keydown.escape.window="open = false"
     class="fixed inset-0 z-[60] flex items-start justify-center bg-slate-900/50 px-4 pt-[12vh] backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Aller à une page">
    <div @click.outside="open = false" class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center gap-3 border-b border-slate-200 px-4">
            <x-icon name="search" class="h-5 w-5 flex-none text-slate-400" />
            <input x-ref="input" x-model="query" @input="active = 0" type="search" autocomplete="off"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go($event)"
                   placeholder="Aller à… (messages, rendez-vous, journal…)" class="h-14 flex-1 border-0 bg-transparent p-0 text-base text-slate-900 focus:ring-0"
                   role="combobox" aria-controls="admin-palette-list" :aria-activedescendant="'admin-palette-' + active" aria-label="Rechercher une page">
            <kbd class="hidden rounded border border-slate-200 px-1.5 text-xs text-slate-400 sm:block">Échap</kbd>
        </div>
        <ul id="admin-palette-list" x-show="results.length" x-ref="list" class="max-h-[50vh] overflow-y-auto py-2" role="listbox">
            <template x-for="(command, i) in results" :key="command.url">
                <li role="option" :id="'admin-palette-' + i" :aria-selected="(i === active).toString()">
                    <a :href="command.url" :target="command.external ? '_blank' : null" @mouseenter="active = i" @click="open = false"
                       :class="i === active ? 'bg-primary-50 text-primary-700' : 'text-slate-800'" class="flex items-center gap-3 px-4 py-2.5 text-sm">
                        <span class="flex-1 font-medium" x-text="command.label"></span>
                        <span class="text-xs text-slate-400" x-text="command.group"></span>
                    </a>
                </li>
            </template>
        </ul>
        <p x-show="! results.length" class="px-4 py-6 text-center text-sm text-slate-500">Aucune page pour « <span x-text="query"></span> ».</p>
        <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">↑ ↓ pour naviguer · Entrée pour ouvrir · Ctrl+Entrée dans un nouvel onglet</p>
    </div>
</div>
