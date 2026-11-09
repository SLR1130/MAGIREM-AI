@props([
    'title',
    'description' => null,
])

<section {{ $attributes->class(['msc-empty-state']) }} role="status">
    @if(isset($icon) && trim((string) $icon) !== '')
        <span class="msc-empty-state__icon" aria-hidden="true">{{ $icon }}</span>
    @endif
    <h3 class="msc-empty-state__title">{{ $title }}</h3>
    @if($description)
        <p class="msc-empty-state__description">{{ $description }}</p>
    @endif
    @if(isset($actions) && trim((string) $actions) !== '')
        <div class="msc-empty-state__actions">{{ $actions }}</div>
    @endif
</section>