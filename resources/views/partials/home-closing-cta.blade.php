<section class='msc-home-closing' aria-labelledby='{{ $guest ? 'mscPublicClosingTitle' : 'mscStudentClosingTitle' }}'>
    <div class='msc-home-section-shell'>
        <div class='msc-home-closing__card' data-msc-reveal>
            <div class='msc-home-closing__copy'>
                <span class='msc-home-section-kicker'>Your next stop</span>
                <h2 id='{{ $guest ? 'mscPublicClosingTitle' : 'mscStudentClosingTitle' }}'>Your tools, close by.</h2>
                <p>{{ $guest ? 'Create an account to explore MAGIREM’s student wellness spaces.' : 'Talk with MAGI or choose a coping activity when you need a pause.' }}</p>
                <div class='msc-home-closing__actions'>
                    <a class='msc-home-btn msc-home-btn--primary' href='{{ $guest ? route('register') : route('student.ai-chat') }}'>{{ $guest ? 'Create my space' : 'Talk to AI Companion' }} <span aria-hidden='true'>&rarr;</span></a>
                    <a class='msc-home-text-link' href='{{ $guest ? route('login') : route('student.coping-toolkit') }}'>{{ $guest ? 'I already have an account' : 'Explore coping tools' }} <span aria-hidden='true'>&nearr;</span></a>
                </div>
            </div>
            <img class='msc-home-closing__foliage' src='{{ asset('assets/branding/magirem-foreground-foliage.webp') }}' alt='' loading='lazy' decoding='async' width='1672' height='941' aria-hidden='true'>
        </div>
    </div>
</section>
