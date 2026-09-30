<x-app-layout>
    <x-slot name="title">Loisirs</x-slot>
    <x-slot name="header">Loisirs</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.loisirs.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Ajouter</a></x-slot>

    @if ($hobbies->isEmpty())
        <x-empty-state icon="heart" message="Aucun loisir pour l'instant." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($hobbies as $hobby)
                <div class="card flex items-center gap-3 p-3">
                    <div class="flex h-14 w-14 flex-none items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-400">
                        @if ($hobby->imageUrl())<img src="{{ $hobby->imageUrl() }}" alt="" class="h-full w-full object-cover">@else<x-icon name="heart" class="h-6 w-6" />@endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-slate-900">{{ $hobby->name }}</p>
                        <p class="text-xs text-slate-500">Ordre {{ $hobby->position }}</p>
                    </div>
                    <x-admin.edit-link :href="route('admin.loisirs.edit', $hobby)" />
                    <x-admin.delete-button :action="route('admin.loisirs.destroy', $hobby)" />
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
