@foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'error' => 'border-red-200 bg-red-50 text-red-800', 'warning' => 'border-amber-200 bg-amber-50 text-amber-900'] as $type => $classes)
    @if (session($type))
        <div x-data="{ show: true }" x-show="show" x-transition role="status"
             {{ $attributes->merge(['class' => "flex items-start justify-between gap-4 rounded-xl border px-4 py-3 text-sm $classes"]) }}>
            <span>{{ session($type) }}</span>
            <button type="button" @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Fermer">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>
    @endif
@endforeach
