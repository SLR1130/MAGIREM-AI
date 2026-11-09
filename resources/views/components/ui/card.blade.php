@props([
    'tone' => 'surface',
    'padding' => 'md',
])

@php
    $toneClass = in_array($tone, ['surface', 'muted', 'mint', 'flat'], true) ? $tone : 'surface';
    $paddingClass = in_array($padding, ['sm', 'md', 'lg'], true) ? $padding : 'md';
@endphp

<section {{ $attributes->class(["msc-card", "msc-card--{$toneClass}", "msc-card--pad-{$paddingClass}"]) }}>
    {{ $slot }}
</section>