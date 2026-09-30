<x-app-layout>
    <x-slot name="title">Pages & visibilité</x-slot>
    <x-slot name="header">Pages & visibilité</x-slot>

    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Une page <strong>privée</strong> n'apparaît plus dans le menu et renvoie une erreur 404 aux visiteurs.
        Les administrateurs connectés continuent de la voir (signalée par un cadenas) ainsi que tout son contenu.
    </div>

    <form method="POST" action="{{ route('admin.pages.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        @foreach ($pages as $page)
            <div class="card grid gap-4 p-4 sm:p-5 md:grid-cols-[1fr_1.5fr_90px_auto] md:items-start" x-data="{ isPublic: @js((bool) old("pages.$page->id.is_public", $page->is_public)) }">
                <div>
                    <label class="form-label" for="page-{{ $page->id }}-title">Titre (menu)</label>
                    <input id="page-{{ $page->id }}-title" type="text" name="pages[{{ $page->id }}][title]" value="{{ old("pages.$page->id.title", $page->title) }}" required maxlength="100" class="form-input">
                    <a href="{{ $page->url() }}" target="_blank" class="mt-1 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-primary-600">{{ parse_url($page->url(), PHP_URL_PATH) ?: '/' }} <x-icon name="external" class="h-3 w-3" /></a>
                    @error("pages.$page->id.title")<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label" for="page-{{ $page->id }}-intro">Introduction</label>
                    <textarea id="page-{{ $page->id }}-intro" name="pages[{{ $page->id }}][intro]" rows="2" maxlength="500" class="form-input">{{ old("pages.$page->id.intro", $page->intro) }}</textarea>
                </div>
                <div>
                    <label class="form-label" for="page-{{ $page->id }}-position">Ordre</label>
                    <input id="page-{{ $page->id }}-position" type="number" min="0" max="999" name="pages[{{ $page->id }}][position]" value="{{ old("pages.$page->id.position", $page->position) }}" class="form-input">
                </div>
                <div>
                    <span class="form-label">Visibilité</span>
                    <input type="hidden" name="pages[{{ $page->id }}][is_public]" :value="isPublic ? 1 : 0">
                    <button type="button" @click="isPublic = !isPublic" role="switch" :aria-checked="isPublic.toString()"
                            :class="isPublic ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800'"
                            class="inline-flex w-32 items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition">
                        <x-icon name="eye" class="h-4 w-4" x-show="isPublic" />
                        <x-icon name="lock" class="h-4 w-4" x-show="!isPublic" x-cloak />
                        <span x-text="isPublic ? 'Publique' : 'Privée'"></span>
                    </button>
                </div>
            </div>
        @endforeach

        <x-admin.form-actions can="pages.write" />
    </form>
</x-app-layout>
