@props(['name', 'label', 'checked' => false, 'help' => null, 'value' => '1'])
<div {{ $attributes->only('class') }}>
    <label class="inline-flex cursor-pointer items-start gap-2">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked))
               class="mt-0.5 rounded border-slate-300 text-primary-600 shadow-sm focus:ring-primary-500">
        <span class="text-sm text-slate-700">{{ $label }}</span>
    </label>
    @if ($help)<p class="form-help ms-6">{{ $help }}</p>@endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</div>
