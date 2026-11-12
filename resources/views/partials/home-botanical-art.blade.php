<div class="msc-home-art msc-home-art--nature" data-msc-hero-art>
    <div class="msc-nature-scene" aria-hidden="true">
        <picture class="msc-nature-scene__picture">
            <source media="(max-width: 600px)" srcset="{{ asset('images/wellness/home-daylight-hero-960.webp') }}">
            <img
                src="{{ asset('images/wellness/home-daylight-hero-1920.webp') }}"
                srcset="{{ asset('images/wellness/home-daylight-hero-960.webp') }} 960w, {{ asset('images/wellness/home-daylight-hero-1920.webp') }} 1920w, {{ asset('images/wellness/home-daylight-hero-3840.webp') }} 3840w"
                sizes="100vw"
                alt=""
                width="3840"
                height="2160"
                fetchpriority="high"
                decoding="async"
            >
        </picture>
        <div class="msc-nature-atmosphere">
            <span class="msc-nature-atmosphere__light"></span>
            <span class="msc-nature-atmosphere__mist"></span>
            <span class="msc-nature-atmosphere__mote"></span>
            <span class="msc-nature-atmosphere__mote"></span>
            <span class="msc-nature-atmosphere__mote"></span>
        </div>
        <div class="msc-nature-scene__shade"></div>
    </div>
    <div class="msc-nature-tools">
        <a class="msc-nature-reset" href="{{ !auth()->check() || !auth()->user()->isStudent() ? route('login') : route('student.coping-toolkit') }}">
            <svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M8 24C5 13 12 6 25 6c0 13-6 20-17 18Z" stroke="currentColor" stroke-width="1.5"/><path d="m7 26 14-15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            <span>Take a small pause <span aria-hidden="true">&rarr;</span></span>
        </a>
    <button class="msc-nature-motion" type="button" data-msc-scene-pause aria-pressed="false" aria-label="Pause homepage motion" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M9 5v14M15 5v14" data-msc-scene-pause-icon /></svg>
        <span data-msc-scene-pause-label>Pause motion</span>
    </button>
    </div>
    <a class="msc-nature-scene__credit" href="https://unsplash.com/photos/photo-of-green-grass-field-at-sunrise-4miBe6zg5r0" target="_blank" rel="noopener noreferrer">Photo: Ales Krivec / Unsplash <span aria-hidden="true">&nearr;</span><span class="msc-sr-only"> (opens in a new tab)</span></a>
</div>
