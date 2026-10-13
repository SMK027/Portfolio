{{-- Remarque : texte avec liens raccourcis, long texte replié, modification en place --}}
@php $long = mb_strlen($remark->body) > 400 || substr_count($remark->body, "\n") > 6; @endphp
<article x-data="{ editing: false, open: false }">
    <div x-show="! editing">
        <div class="flex items-center justify-between gap-2">
            <p class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                <time datetime="{{ $remark->noted_on->toDateString() }}">{{ $remark->noted_on->translatedFormat('j F Y') }}</time>
                @if ($highlight)<span class="badge-primary">Dernière remarque</span>@endif
            </p>
            <div class="-my-1 flex flex-none">
                @if ($canEditRemark)
                    <button type="button" class="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-400 hover:bg-primary-50 hover:text-primary-600" @click="editing = true" title="Modifier" aria-label="Modifier">
                        <x-icon name="pencil" class="h-4 w-4" />
                    </button>
                @endif
                @if ($canDeleteRemark)
                    <x-admin.delete-button class="!p-1.5" :action="route('admin.internship.remarks.destroy', $remark)" confirm="Supprimer cette remarque ?" />
                @endif
            </div>
        </div>
        <div @if ($long) class="relative" :class="open || 'max-h-48 overflow-hidden'" @endif>
            <p @class(['mt-1.5 whitespace-pre-line break-words text-sm leading-relaxed [overflow-wrap:anywhere]', 'text-slate-800' => $highlight, 'text-slate-600' => ! $highlight])>{{ \App\Support\TextLinks::render($remark->body) }}</p>
            @if ($long)<div class="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-white" x-show="! open"></div>@endif
        </div>
        @if ($long)
            <button type="button" class="mt-1 text-xs font-medium text-primary-600 hover:underline" @click="open = ! open" x-text="open ? 'Réduire' : 'Lire la suite'"></button>
        @endif
    </div>

    @if ($canEditRemark)
        <form method="POST" action="{{ route('admin.internship.remarks.update', $remark) }}" class="grid gap-3 sm:grid-cols-[11rem_1fr]" x-show="editing" x-cloak>
            @csrf
            @method('PUT')
            <input type="date" name="noted_on" value="{{ $remark->noted_on->toDateString() }}" class="form-input" required aria-label="Date">
            <textarea name="body" rows="8" class="form-input" maxlength="5000" required aria-label="Remarque">{{ $remark->body }}</textarea>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <button type="button" class="btn-secondary btn-sm" @click="editing = false">Annuler</button>
                <button type="submit" class="btn-primary btn-sm"><x-icon name="check" class="h-4 w-4" /> Enregistrer</button>
            </div>
        </form>
    @endif
</article>
