@php($homeMagiCanChat = ($insightRecommendationState['state'] ?? 'consent_required') !== 'consent_required')
<link rel="stylesheet" href="{{ asset('css/home-magi-chat.css') }}">

<div class="home-magi" data-home-magi
     data-chat-url="/api/ai/chat"
     data-session-url="/api/ai/sessions"
     data-full-chat-url="{{ route('student.ai-chat', [], false) }}"
     data-ai-enabled="{{ $homeMagiCanChat ? 'true' : 'false' }}"
     data-request-timeout="{{ (int) config('services.mindcare_ml.chat.browser_timeout_ms', 210000) }}">
    <div class="home-magi__nudge" data-magi-nudge hidden>
        <div class="home-magi__nudge-header">
            <span><span class="home-magi__online-dot" aria-hidden="true"></span> MAGI <small>quick hello</small></span>
            <button type="button" class="home-magi__nudge-dismiss" data-magi-dismiss aria-label="Dismiss MAGI greeting">&times;</button>
        </div>
        <button type="button" class="home-magi__nudge-open" data-magi-open>
            <span data-magi-nudge-text aria-hidden="true"></span>
            <span class="home-magi__nudge-reply">Chat with MAGI <span aria-hidden="true">&rarr;</span></span>
        </button>
        <span class="home-magi__sr-only" data-magi-nudge-announcement aria-live="polite"></span>
    </div>

    <section id="home-magi-panel" class="home-magi__panel" aria-labelledby="home-magi-title" hidden>
        <header class="home-magi__header">
            <span class="home-magi__header-avatar" aria-hidden="true"><x-magi-avatar size="sm" state="idle" /></span>
            <span class="home-magi__header-copy"><strong id="home-magi-title">Chat with MAGI</strong><small>Your AI companion</small></span>
            <button type="button" class="home-magi__close" data-magi-close aria-label="Close MAGI chat">&times;</button>
        </header>

        <div class="home-magi__body">
            <div class="home-magi__messages" data-magi-messages role="log" aria-label="MAGI conversation" aria-live="polite" aria-relevant="additions">
                <div class="home-magi__message home-magi__message--assistant"><span class="home-magi__bubble">Hi! Kumusta ka? Ano ang gusto mong pag-usapan?</span></div>
            </div>
            <div class="home-magi__thinking" data-magi-thinking role="status" hidden><span aria-hidden="true"><i></i><i></i><i></i></span> MAGI is thinking&hellip;</div>
            <div class="home-magi__error" data-magi-error role="alert" hidden>
                <span data-magi-error-text></span>
                <button type="button" data-magi-retry hidden>Retry safely</button>
                <a href="{{ route('student.privacy') }}" data-magi-privacy-link hidden>Privacy settings &rarr;</a>
            </div>

            @if(!$homeMagiCanChat)
                <p class="home-magi__consent">To chat with MAGI, enable AI recommendations in <a href="{{ route('student.privacy') }}">Privacy Settings</a>.</p>
            @else
                <div class="home-magi__prompts" aria-label="Conversation starters" data-magi-prompts>
                    <button type="button" data-magi-prompt="May gusto akong ikuwento.">May gusto akong ikuwento</button>
                    <button type="button" data-magi-prompt="Help me plan my day.">Help me plan my day</button>
                </div>
            @endif

            <form class="home-magi__form" data-magi-form>
                <label class="home-magi__sr-only" for="home-magi-message">Message MAGI</label>
                <textarea id="home-magi-message" data-magi-input rows="1" maxlength="1000" placeholder="Type a message&hellip;" @disabled(!$homeMagiCanChat)></textarea>
                <button type="submit" data-magi-send aria-label="Send message" @disabled(!$homeMagiCanChat)><span aria-hidden="true">&uarr;</span></button>
            </form>
            <p class="home-magi__privacy-note">Messages follow your AI Chat privacy settings. MAGI is not an emergency service.</p>
            <a class="home-magi__full-chat" href="{{ route('student.ai-chat', [], false) }}" data-magi-full-chat>Open full AI Chat &rarr;</a>

            <details class="home-magi__resources">
                <summary>MAGI Recommends <span aria-hidden="true">&darr;</span></summary>
                <p>Wellness resources selected for you</p>
                @include('student.partials.recommendation-availability', ['recommendationState' => $insightRecommendationState])
                <a href="{{ route('student.insights') }}#recommendations">View all insights &rarr;</a>
            </details>
        </div>
    </section>

    <button type="button" class="home-magi__toggle" aria-label="Open MAGI chat" aria-controls="home-magi-panel" aria-expanded="false" data-magi-toggle>
        <x-magi-avatar size="lg" state="idle" animated data-intro="false" data-avatar-context="home" aria-hidden="true" />
    </button>
</div>
<script src="{{ asset('js/home-magi-chat.js') }}" defer></script>
