<section class='msc-home-support msc-home-support--story' id='{{ $guest ? 'mscPublicSupport' : 'mscStudentSupport' }}' aria-labelledby='{{ $guest ? 'mscPublicSupportTitle' : 'mscStudentSupportTitle' }}'>
    <div class='msc-support-story'>
        <div class='msc-support-story__visual' data-msc-reveal data-msc-tilt aria-hidden='true'>
            <img src='{{ asset('images/wellness/home-guidance-conversation-v1.webp') }}' alt='' loading='lazy' decoding='async' width='1200' height='676'>
            <span class='msc-support-story__visual-label'>Guidance &amp; support</span>
        </div>
        <div class='msc-support-story__content' data-msc-reveal style='--msc-reveal-order:1'>
            <span class='msc-home-section-kicker'>A person, when you need one</span>
            <h2 id='{{ $guest ? 'mscPublicSupportTitle' : 'mscStudentSupportTitle' }}'>There is room for a real conversation.</h2>
            <p>Explore counselor profiles and appointment options when talking to a person feels right.</p>
            <div class='msc-support-story__actions'>
                <a class='msc-home-btn msc-home-btn--primary' href='{{ $guest ? route('login') : route('student.counselor-directory') }}'>{{ $guest ? 'Sign in to continue' : 'Find a counselor' }} <span aria-hidden='true'>&rarr;</span></a>
                <a class='msc-home-text-link' href='{{ $guest ? route('register') : route('student.appointments') }}'>{{ $guest ? 'Create an account' : 'View appointments' }} <span aria-hidden='true'>&nearr;</span></a>
            </div>
            <nav class='msc-support-story__shortcuts' aria-label='More wellness spaces'>
                <a href='{{ $guest ? route('login') : route('student.coping-toolkit') }}'>Coping Toolkit <span aria-hidden='true'>&nearr;</span></a>
                <a href='{{ $guest ? route('login') : route('student.community') }}'>Community Circle <span aria-hidden='true'>&nearr;</span></a>
                <a href='{{ $guest ? route('login') : route('student.resources') }}'>Wellness Resources <span aria-hidden='true'>&nearr;</span></a>
            </nav>
        </div>
    </div>
</section>
