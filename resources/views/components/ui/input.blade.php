@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php($inputId = $id ?: $name)
<div class="msc-field">
    @if($label)
        <label class="msc-field__label" @if($inputId) for="{{ $inputId }}" @endif>
            {{ $label }} @if($required)<span aria-hidden="true">*</span>@endif
        </label>
    @endif
    <input
        @if($inputId) id="{{ $inputId }}" @endif
        @if($name) name="{{ $name }}" @endif
        type="{{ $type }}"
        @if($required) required @endif
        @if($error) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
        {{ $attributes->class(['msc-input']) }}
    >
    @if($error)
        <p class="msc-field__error" id="{{ $inputId }}-error" role="alert">{{ $error }}</p>
    @elseif($hint)
        <p class="msc-field__hint" @if($inputId) id="{{ $inputId }}-hint" @endif>{{ $hint }}</p>
    @endif
</div>