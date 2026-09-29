@props(['name', 'label' => null, 'value' => null, 'help' => null, 'required' => false, 'rows' => 4])
@php $id = 'f-'.str_replace(['[', ']', '.'], '-', $name); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
              {{ $attributes->except('class')->merge(['class' => 'form-input']) }}>{{ old($name, $value) }}</textarea>
    @if ($help)<p class="form-help">{{ $help }}</p>@endif
    @error($name)<p class="form-error">{{ $message }}</p>@enderror
</div>
