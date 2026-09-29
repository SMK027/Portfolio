{{-- Téléversement d'image avec aperçu et option de suppression --}}
@props(['name', 'label' => null, 'current' => null, 'help' => null, 'accept' => 'image/png,image/jpeg,image/webp,image/gif', 'rounded' => 'rounded-lg'])
<div {{ $attributes->only('class') }} x-data="{ preview: @js($current), removed: false }">
    @if ($label)<span class="form-label">{{ $label }}</span>@endif
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex h-24 w-32 flex-none items-center justify-center overflow-hidden border border-dashed border-slate-300 bg-slate-50 {{ $rounded }}">
            <template x-if="preview && !removed"><img :src="preview" alt="" class="h-full w-full object-cover"></template>
            <template x-if="!preview || removed"><x-icon name="photo" class="h-8 w-8 text-slate-300" /></template>
        </div>
        <div class="min-w-0 flex-1 space-y-2">
            <input type="file" name="{{ $name }}" accept="{{ $accept }}"
                   @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); removed = false; }"
                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100">
            @if ($current)
                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remove_{{ $name }}" value="1" x-model="removed" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                    Supprimer l'image actuelle
                </label>
            @endif
        </div>
    </div>
    @if ($help)<p class="form-help">{{ $help }}</p>@endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</div>
