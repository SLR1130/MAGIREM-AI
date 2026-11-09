<div class="magi-recommendations" data-recommendation-state="{{ $recommendationState['state'] }}" data-recommendation-url="{{ route('student.insights.recommendations') }}">
<div class="magi-recommendations__content" aria-live="polite">
@if($recommendationState['state'] === 'consent_required')
    <h2>MAGI is getting to know you.</h2>
    <p>Personalized recommendations require your current permission. You can review your choice in Privacy Settings.</p>
    <a class="mi-modern-link" href="{{ route('student.privacy') }}">Review privacy settings &rarr;</a>
@elseif($recommendationState['state'] === 'getting_to_know_you')
    <h2>MAGI is getting to know you.</h2>
    <p>Recommendations can become more relevant as you interact with MAGI. You can explore the resource library whenever you like.</p>
    <a class="mi-modern-link" href="{{ route('student.resources') }}">Browse wellness resources &rarr;</a>
@else
    <h2>Ready when you are</h2>
    <p>With your permission, MAGI will use short excerpts from your recent visible conversations to suggest resources from the wellness library. Journal-only entries are not used.</p>
@endif
</div>
@if($recommendationState['state'] === 'idle')
    <button type="button" class="magi-recommendations__refresh">Find resources from my chats</button>
@endif
</div>
<style>
    .magi-recommendations__item { border:1px solid #dce9e1; border-radius:12px; padding:.8rem; margin:.7rem 0; }
    .magi-recommendations__item h3 { margin:0 0 .3rem; font-size:1rem; color:#174337; }
    .magi-recommendations__item p { font-size:.9rem; margin:.4rem 0; }
    .magi-recommendations__item a { color:#12664b; font-weight:700; }
    .magi-recommendations__topics { display:flex; flex-wrap:wrap; gap:.4rem; margin:.6rem 0; }
    .magi-recommendations__topics span { background:#edf6ef; color:#315d49; border-radius:999px; padding:.3rem .6rem; font-size:.82rem; }
    .magi-recommendations__refresh { border:1px solid #b8d3c1; border-radius:9px; padding:.55rem .8rem; color:#174337; background:#fff; cursor:pointer; font:inherit; }
</style>
<script>
(() => {
    const root = document.currentScript.previousElementSibling.previousElementSibling;
    if (!root || !root.classList.contains('magi-recommendations')) return;
    const content = root.querySelector('.magi-recommendations__content');
    const refresh = root.querySelector('.magi-recommendations__refresh');
    let loaded = false, pending = false;
    function element(tag, text, className) {
        const node = document.createElement(tag);
        node.textContent = text;
        if (className) node.className = className;
        return node;
    }
    function render(result) {
        content.replaceChildren();
        if (result.state === 'ready') {
            content.append(element('p', result.introduction));
            const topics = element('div', '', 'magi-recommendations__topics');
            result.topics.forEach(topic => topics.append(element('span', topic)));
            content.append(topics);
            result.items.forEach(item => {
                const card = element('article', '', 'magi-recommendations__item');
                card.append(element('h3', item.title), element('small', item.category), element('p', item.reason));
                const link = element('a', 'View Resource →');
                link.href = item.url;
                card.append(link); content.append(card);
            });
        } else {
            const states = {
                consent_required: ['Choose what you share', 'Personalized recommendations require your current permission.'],
                getting_to_know_you: ['MAGI is getting to know you.', 'Recommendations can become more relevant as you interact with MAGI.'],
                no_resources: ['No resources available yet', 'There are no active resources in the library right now.'],
                no_match: ['No suitable match yet', 'MAGI could not find a suitable resource for the available context.'],
                unavailable: ['Recommendations are temporarily unavailable', 'Please try again later or browse the resource library.'],
                busy: ['MAGI is busy', 'Please try again in a moment.']
            };
            const state = states[result.state] || states.unavailable;
            content.append(element('h2', state[0]), element('p', state[1]));
            const link = element('a', result.state === 'consent_required' ? 'Review privacy settings →' : 'Browse wellness resources →');
            link.href = result.state === 'consent_required' ? @json(route('student.privacy')) : @json(route('student.resources'));
            content.append(link);
        }
    }
    async function load(force = false) {
        if (pending || (!force && (loaded || root.dataset.recommendationState !== 'idle'))) return;
        pending = true;
        content.replaceChildren(element('p', 'MAGI is looking through your permitted conversations and available resources…'));
        if (refresh) refresh.hidden = true;
        const abort = new AbortController();
        const timer = setTimeout(() => abort.abort(), 80000);
        try {
            const response = await fetch(root.dataset.recommendationUrl, {
                method: 'POST', credentials: 'same-origin', signal: abort.signal,
                headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({refresh: force, consent_to_history_use: true})
            });
            if (!response.ok) throw new Error('unavailable');
            const result = await response.json();
            render(result);
            loaded = true;
            if (refresh && result.state !== 'consent_required') {
                refresh.textContent = 'Refresh suggestions';
                refresh.hidden = false;
            }
        } catch (_) {
            render({state:'unavailable'});
            if (refresh) refresh.hidden = false;
        } finally {
            clearTimeout(timer); pending = false;
        }
    }
    if (refresh) refresh.addEventListener('click', () => load(loaded));
})();
</script>
