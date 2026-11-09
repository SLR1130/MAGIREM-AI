@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
])

@php
    $variantClass = in_array($variant, ['primary', 'secondary', 'ghost'], true) ? $variant : 'primary';
    $sizeClass = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class(["msc-btn", "msc-btn--{$variantClass}", "msc-btn--{$sizeClass}"]) }} @if($disabled) aria-disabled="true" @endif>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->class(["msc-btn", "msc-btn--{$variantClass}", "msc-btn--{$sizeClass}"]) }}>
        {{ $slot }}
    </button>
@endif