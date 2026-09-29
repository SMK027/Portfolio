@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])
@php $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name)); $key = str_replace(['[', ']'], ['.', ''], $name); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'file' && $type !== 'password') value="{{ old($key, $value) }}" @endif
           @required($required)
           {{ $attributes->except(['class', 'id'])->merge(['class' => $type === 'file'
                ? 'block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100'
                : 'form-input']) }}>
    @if ($help)<p class="form-help">{{ $help }}</p>@endif
    @error($key)<p class="form-error">{{ $message }}</p>@enderror
</div>
