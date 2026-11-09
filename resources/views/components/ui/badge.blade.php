@props(['tone' => 'neutral'])

@php($toneClass = in_array($tone, ['neutral', 'mint', 'gold'], true) ? $tone : 'neutral')
<span {{ $attributes->class(["msc-badge", "msc-badge--{$toneClass}"]) }}>
    {{ $slot }}
</span>