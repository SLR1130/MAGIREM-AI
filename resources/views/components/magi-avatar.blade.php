@props([
    'size' => 'sm',
    'state' => 'idle',
    'label' => 'MAGI AI companion',
    'animated' => false,
])

@php
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'sm';
    $state = in_array($state, ['idle', 'thinking', 'responding', 'error'], true) ? $state : 'idle';
    $dimensions = ['sm' => 31, 'md' => 42, 'lg' => 54];
    $intro = filter_var($attributes->get('data-intro', 'false'), FILTER_VALIDATE_BOOLEAN);
@endphp

@once
    @push('head')
    <link rel="stylesheet" href="{{ asset('css/magi-avatar.css') }}">
    <script src="{{ asset('js/magi-sprite.js') }}" defer></script>
    @endpush
@endonce

<span {{ $attributes->merge([
    'class' => "magi-avatar magi-avatar--{$size}",
    'data-magi-avatar' => '',
    'data-state' => $state,
    'data-animated' => $animated ? 'true' : 'false',
    'data-intro' => $intro ? 'true' : 'false',
    'role' => 'img',
    'aria-label' => $label,
]) }}>
    <img
        class="magi-avatar__static"
        src="{{ asset('assets/branding/magi-avatar-static.png') }}"
        width="{{ $dimensions[$size] }}"
        height="{{ $dimensions[$size] }}"
        alt=""
        aria-hidden="true"
        loading="{{ $animated ? 'lazy' : 'eager' }}"
        decoding="async"
    >
    @if($animated)
        <span class="magi-avatar__motion" aria-hidden="true">
            <canvas
                class="magi-avatar__canvas"
                width="{{ $dimensions[$size] }}"
                height="{{ $dimensions[$size] }}"
                data-sprite-src="{{ asset('assets/branding/magi-sprite-normalized.png') }}"
                data-sprite-columns="11"
                data-sprite-rows="4"
                data-sprite-frames="44"
                data-sprite-intro-count="4"
                data-sprite-idle-start="4"
                aria-hidden="true"
            ></canvas>
        </span>
    @endif
</span>
