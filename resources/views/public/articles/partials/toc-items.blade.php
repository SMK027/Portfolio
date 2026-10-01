{{-- Éléments du sommaire (récursif) : un chevron replie ou déplie les sous-titres --}}
<ol @class(['space-y-1', 'border-l border-slate-200' => $depth === 0, 'mt-1' => $depth > 0]) @if ($depth > 0) x-show="isOpen(@js($parentId))" x-transition.opacity @endif>
    @foreach ($items as $item)
        <li>
            <div class="flex items-start" style="padding-left: {{ $depth * 0.75 }}rem">
                <a href="#{{ $item['id'] }}" @click="go(@js($item['id']), $el)"
                   :class="shown === @js($item['id']) ? 'border-primary-500 text-primary-700 font-medium' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300'"
                   class="-ml-px block flex-1 border-l-2 py-1 pl-3 pr-1 transition">{{ $item['text'] }}</a>
                @if ($item['children'])
                    <button type="button" @click="toggle(@js($item['id']))" class="mt-0.5 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            :aria-expanded="isOpen(@js($item['id'])).toString()" :aria-label="(isOpen(@js($item['id'])) ? 'Replier' : 'Déplier') + ' « ' + @js($item['text']) + ' »'">
                        <span class="block transition" :class="isOpen(@js($item['id'])) && 'rotate-90'"><x-icon name="chevron-right" class="h-4 w-4" /></span>
                    </button>
                @endif
            </div>
            @if ($item['children'])
                @include('public.articles.partials.toc-items', ['items' => $item['children'], 'depth' => $depth + 1, 'parentId' => $item['id']])
            @endif
        </li>
    @endforeach
</ol>
