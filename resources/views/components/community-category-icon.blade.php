@props(['name' => 'sparkles'])

@php($iconName = in_array($name, ['book-open', 'brain', 'heart', 'sprout', 'message-circle', 'sparkles', 'lightbulb', 'shield-check', 'image-plus', 'thumbs-up', 'house', 'pencil', 'circle-help'], true) ? $name : 'sparkles')

<svg {{ $attributes->merge(['class' => 'community-category-icon']) }} data-community-icon="{{ $iconName }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($iconName)
        @case('book-open')
            <path d="M12 7v14"/><path d="M3 18V5a2 2 0 0 1 2-2h4a3 3 0 0 1 3 3 3 3 0 0 1 3-3h4a2 2 0 0 1 2 2v13"/><path d="M3 18a2 2 0 0 0 2 2h4a3 3 0 0 1 3 1 3 3 0 0 1 3-1h4a2 2 0 0 0 2-2"/>
            @break
        @case('brain')
            <path d="M12 18V5"/><path d="M15 13a4.17 4.17 0 0 1-3-4 4.17 4.17 0 0 1-3 4"/><path d="M17.6 6.5A3 3 0 1 0 12 5a3 3 0 1 0-5.6 1.5"/><path d="M18 5.1a4 4 0 0 1 2.8 4.3"/><path d="M21.2 12a4 4 0 0 1-3.2 4"/><path d="M16 21a3 3 0 0 0 3-3.5"/><path d="M6 5.1a4 4 0 0 0-2.8 4.3"/><path d="M2.8 12A4 4 0 0 0 6 16"/><path d="M8 21a3 3 0 0 1-3-3.5"/>
            @break
        @case('heart')
            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>
            @break
        @case('sprout')
            <path d="M12 22v-8"/><path d="M12 14c-4 0-7-2.2-7-6 4.2 0 7 2.1 7 6Z"/><path d="M12 11c0-4.5 2.8-7 7-7 0 3.8-2.5 7-7 7Z"/><path d="M5 22h14"/>
            @break
        @case('message-circle')
            <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/>
            @break
        @case('lightbulb')
            <path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.1 14a6 6 0 1 0-6.2 0c.6.4 1.1 1.1 1.1 2h4c0-.9.5-1.6 1.1-2Z"/>
            @break
        @case('shield-check')
            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('image-plus')
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/><path d="M19 8v6M16 11h6"/>
            @break
        @case('thumbs-up')
            <path d="M7 10v12H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h3Z"/><path d="M7 10l5-7a3 3 0 0 1 2 3l-1 4h6a2 2 0 0 1 2 2l-2 8a2 2 0 0 1-2 2H7"/>
            @break
        @case('house')
            <path d="m3 10 9-7 9 7"/><path d="M5 9v12h14V9"/><path d="M9 21v-7h6v7"/>
            @break
        @case('pencil')
            <path d="m16 4 4 4"/><path d="m3 21 4.5-1 11-11a2.8 2.8 0 0 0-4-4l-11 11L3 21Z"/>
            @break
        @case('circle-help')
            <circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>
            @break
        @default
            <path d="M12 3v3"/><path d="M12 18v3"/><path d="m5.6 5.6 2.1 2.1"/><path d="m16.3 16.3 2.1 2.1"/><path d="M3 12h3"/><path d="M18 12h3"/><path d="m5.6 18.4 2.1-2.1"/><path d="m16.3 7.7 2.1-2.1"/><path d="m10 10 2-6 2 6 6 2-6 2-2 6-2-6-6-2 6-2Z"/>
    @endswitch
</svg>
