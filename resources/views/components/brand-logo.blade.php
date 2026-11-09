@props([
    'variant' => 'emblem',
    'alt' => null,
    'class' => '',
])

@php
    $isFull = $variant === 'full';
    $assetPath = config('branding.assets.' . ($isFull ? 'full' : 'emblem'));
    $logoAlt = $alt ?? ($isFull
        ? config('branding.name') . ' — ' . config('branding.tagline')
        : config('branding.name') . ' emblem');
@endphp

<img
    class="magirem-logo magirem-logo--{{ $isFull ? 'full' : 'emblem' }} {{ $class }}"
    src="{{ asset($assetPath) }}"
    alt="{{ $logoAlt }}"
    width="{{ $isFull ? 1229 : 850 }}"
    height="{{ $isFull ? 1171 : 850 }}"
    decoding="async"
>
