@props(['level'])
@if ($level)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5']) }} title="{{ \App\Models\Skill::LEVEL_LABELS[$level] ?? '' }}">
        @for ($i = 1; $i <= \App\Models\Skill::MAX_LEVEL; $i++)
            <span class="h-1.5 w-4 rounded-full {{ $i <= $level ? 'bg-primary-500' : 'bg-slate-200' }}"></span>
        @endfor
        <span class="sr-only">Niveau : {{ \App\Models\Skill::LEVEL_LABELS[$level] ?? $level }}</span>
    </span>
@endif
