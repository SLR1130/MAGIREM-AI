@php
    $pathwayPrefix = $guest ? 'mscPublicPathway' : 'mscStudentPathway';
    $pathways = [
        ['id' => 'talk', 'label' => 'Talk with MAGI', 'kicker' => 'AI Companion', 'title' => 'A conversation can be a first step.', 'description' => 'Put what is on your mind into words and explore a practical next step with MAGI, at your own pace.', 'cta' => $guest ? route('login') : route('student.ai-chat'), 'cta_label' => $guest ? 'Sign in to begin' : 'Open AI Companion', 'secondary' => $guest ? route('register') : route('student.counselor-directory'), 'secondary_label' => $guest ? 'Create an account' : 'Find a counselor', 'note' => 'AI-supported conversation', 'image' => 'images/wellness/home-daylight-workspace-1600.webp'],
        ['id' => 'reflect', 'label' => 'Write it down', 'kicker' => 'My Journal', 'title' => 'Give your thoughts a page.', 'description' => 'Write freely, use an optional prompt, and come back to your reflections whenever you are ready.', 'cta' => $guest ? route('login') : route('student.journal'), 'cta_label' => $guest ? 'Sign in to begin' : 'Open my Journal', 'secondary' => $guest ? route('register') : route('student.resources'), 'secondary_label' => $guest ? 'Create an account' : 'Browse resources', 'note' => 'A space for personal reflection', 'image' => 'images/wellness/home-daylight-journal-1600.webp'],
        ['id' => 'reset', 'label' => 'Take a breather', 'kicker' => 'Coping Toolkit', 'title' => 'Make a little room to breathe.', 'description' => 'Choose a grounding activity, breathing guide, or practical coping tool for a pause in your day.', 'cta' => $guest ? route('login') : route('student.coping-toolkit'), 'cta_label' => $guest ? 'Sign in to begin' : 'Explore the Coping Toolkit', 'secondary' => $guest ? route('register') : route('student.resources'), 'secondary_label' => $guest ? 'Create an account' : 'See wellness resources', 'note' => 'Small tools for the moment', 'image' => 'images/wellness/home-daylight-hero-1920.webp'],
        ['id' => 'connect', 'label' => 'Find your people', 'kicker' => 'Community Circle', 'title' => 'Support can start with connection.', 'description' => 'Explore respectful conversations with fellow students or see your options for Guidance support.', 'cta' => $guest ? route('login') : route('student.community'), 'cta_label' => $guest ? 'Sign in to begin' : 'Visit Community', 'secondary' => $guest ? route('register') : route('student.appointments'), 'secondary_label' => $guest ? 'Create an account' : 'View appointments', 'note' => 'Community, with choice', 'image' => 'images/wellness/home-daylight-community-1600.webp'],
    ];
@endphp

<section class='msc-home-pathways' id='{{ $guest ? 'mscPublicPathways' : 'mscStudentPathways' }}' aria-labelledby='{{ $guest ? 'mscPublicPathwaysTitle' : 'mscStudentPathwaysTitle' }}'>
    <div class='msc-home-section-shell'>
        <header class='msc-home-section-heading' data-msc-reveal>
            <span class='msc-home-section-kicker'>Spaces for student life</span>
            <div>
                <h2 id='{{ $guest ? 'mscPublicPathwaysTitle' : 'mscStudentPathwaysTitle' }}'>Talk. Write. Breathe. Connect.</h2>
                <p>Choose a MAGIREM space to explore—there is no set order.</p>
            </div>
        </header>
        <div class='msc-pathways' data-msc-pathways data-msc-reveal style='--msc-reveal-order:1'>
            <div class='msc-pathways__tabs' role='tablist' aria-label='Choose a wellness direction'>
                @foreach($pathways as $index => $pathway)
                    <button class='msc-pathways__tab {{ $index === 0 ? 'is-active' : '' }}' id='{{ $pathwayPrefix }}Tab{{ ucfirst($pathway['id']) }}' type='button' role='tab' aria-selected='{{ $index === 0 ? 'true' : 'false' }}' aria-controls='{{ $pathwayPrefix }}Panel{{ ucfirst($pathway['id']) }}' tabindex='{{ $index === 0 ? '0' : '-1' }}' data-msc-pathway-tab='{{ $pathway['id'] }}' data-msc-reveal data-msc-spotlight data-msc-tilt style='--msc-reveal-order:{{ $index }}'>
                        <span class='msc-pathways__tab-thumb' aria-hidden='true'><img src='{{ asset($pathway['image']) }}' alt='' loading='lazy' decoding='async' width='1200' height='676'></span>
                        <span class='msc-pathways__tab-copy'>
                            <small>{{ $pathway['kicker'] }}</small>
                            <strong>{{ $pathway['label'] }}</strong>
                        </span>
                        <span class='msc-pathways__tab-arrow' aria-hidden='true'>&rarr;</span>
                    </button>
                @endforeach
            </div>
            <div class='msc-pathways__panels' data-msc-spotlight>
                @foreach($pathways as $index => $pathway)
                    <article class='msc-pathways__panel {{ $index === 0 ? 'is-active' : '' }}' id='{{ $pathwayPrefix }}Panel{{ ucfirst($pathway['id']) }}' role='tabpanel' aria-labelledby='{{ $pathwayPrefix }}Tab{{ ucfirst($pathway['id']) }}' data-msc-pathway-panel='{{ $pathway['id'] }}' @if($index !== 0) hidden @endif>
                        <div class='msc-pathways__visual' aria-hidden='true' data-msc-tilt>
                            <img class='msc-pathways__visual-image' src='{{ asset($pathway['image']) }}' alt='' loading='lazy' decoding='async' width='1200' height='676'>
                            <span class='msc-pathways__visual-label'>{{ $pathway['kicker'] }}</span>
                        </div>
                        <div class='msc-pathways__content'>
                            <span class='msc-pathways__kicker'>{{ $pathway['kicker'] }}</span>
                            <h3>{{ $pathway['title'] }}</h3>
                            <p>{{ $pathway['description'] }}</p>
                            <span class='msc-pathways__note'><span aria-hidden='true'>&#10003;</span> {{ $pathway['note'] }}</span>
                            <div class='msc-pathways__actions'>
                                <a class='msc-home-btn msc-home-btn--primary' href='{{ $pathway['cta'] }}'>{{ $pathway['cta_label'] }} <span aria-hidden='true'>&rarr;</span></a>
                                <a class='msc-home-text-link' href='{{ $pathway['secondary'] }}'>{{ $pathway['secondary_label'] }} <span aria-hidden='true'>&nearr;</span></a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>
