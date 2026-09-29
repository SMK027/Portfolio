@props(['icon' => 'folder', 'message' => 'Aucun élément pour le moment.'])
<div {{ $attributes->merge(['class' => 'card flex flex-col items-center justify-center gap-3 px-6 py-16 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icon :name="$icon" class="h-6 w-6" /></span>
    <p class="text-sm text-slate-500">{{ $message }}</p>
    {{ $slot }}
</div>
