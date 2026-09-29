<x-app-layout>
    <x-slot name="title">Thèmes</x-slot>
    <x-slot name="header">Thèmes</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.themes.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a></x-slot>

    <p class="text-sm text-slate-500">Les thèmes classent les projets (programmation, réseau, projets personnels…) et les articles de veille.</p>

    @if ($themes->isEmpty())
        <x-empty-state icon="tag" message="Aucun thème pour l'instant." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($themes as $theme)
                <div class="card overflow-hidden">
                    <div class="relative aspect-[16/7] bg-gradient-to-br from-primary-600 to-accent-600">
                        @if ($theme->backgroundUrl())
                            <img src="{{ $theme->backgroundUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                        @endif
                        <span class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent"></span>
                        <span class="absolute bottom-3 left-4 font-display text-lg font-bold text-white">{{ $theme->name }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 px-4 py-3">
                        <span class="text-xs text-slate-500">{{ $theme->projects_count }} projet(s) · {{ $theme->articles_count }} article(s) · ordre {{ $theme->position }}</span>
                        <span class="flex-none">
                            <x-admin.edit-link :href="route('admin.themes.edit', $theme)" />
                            <x-admin.delete-button :action="route('admin.themes.destroy', $theme)" confirm="Supprimer ce thème ? Les projets et articles associés seront conservés." />
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
