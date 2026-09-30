@props(['theme', 'active' => false])
<a href="{{ route('projects.theme', $theme) }}"
   @class([
       'group relative flex aspect-[16/9] items-end overflow-hidden rounded-2xl bg-gradient-to-br from-primary-600 to-accent-600 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg',
       'ring-4 ring-primary-400 ring-offset-2' => $active,
   ])>
    @if ($theme->backgroundUrl())
        <img src="{{ $theme->backgroundUrl() }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105">
    @endif
    <span class="absolute inset-0 bg-gradient-to-t from-slate-900/85 via-slate-900/30 to-transparent"></span>
    <span class="relative p-5">
        <span class="block font-display text-xl font-bold text-white">{{ $theme->name }}</span>
        <span class="text-sm text-slate-200">{{ $theme->projects_count }} {{ \Illuminate\Support\Str::plural('projet', $theme->projects_count) }}</span>
    </span>
</a>
