<x-app-layout>
    <x-slot name="title">Pages & visibilité</x-slot>
    <x-slot name="header">Pages & visibilité</x-slot>

    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <ul class="space-y-1">
            <li><strong>Publique</strong> : dans le menu, la recherche et le plan du site. <strong>Protégée</strong> : dans le menu, contenu déverrouillé par mot de passe.</li>
            <li><strong>Non indexée</strong> : accessible par son adresse, absente du menu, de la recherche et du plan du site.</li>
            <li><strong>Maintenance</strong> : absente du menu, page « en maintenance » pour les visiteurs.</li>
            <li><strong>Brouillon</strong> / <strong>Privée</strong> : erreur 404 pour les visiteurs.</li>
        </ul>
        <p class="mt-2">Les administrateurs connectés voient toujours toutes les pages et leur contenu.</p>
    </div>

    <form method="POST" action="{{ route('admin.pages.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        @foreach ($pages as $page)
            <div class="card grid gap-4 p-4 sm:p-5 md:grid-cols-[1fr_1.5fr_90px_220px] md:items-start" x-data="{ status: @js(old("pages.$page->id.status", $page->status)) }">
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
                <div class="space-y-2">
                    <label class="form-label" for="page-{{ $page->id }}-status">Statut</label>
                    <select id="page-{{ $page->id }}-status" name="pages[{{ $page->id }}][status]" x-model="status" class="form-input">
                        @foreach (\App\Models\Page::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(old("pages.$page->id.status", $page->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div x-show="status === 'password'" x-cloak>
                        <input type="password" name="pages[{{ $page->id }}][password]" autocomplete="new-password" maxlength="100" class="form-input"
                               aria-label="Mot de passe de la page" placeholder="{{ $page->password ? 'Inchangé si vide' : 'Mot de passe (4 caractères min.)' }}">
                    </div>
                    @error("pages.$page->id.password")<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
        @endforeach

        <x-admin.form-actions can="pages.write" />
    </form>
</x-app-layout>
