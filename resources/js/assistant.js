// Lesson assistant panel (T-P7-03, AI spec 3 and 15). The server decides eligibility, validates and answers; this module only
// pauses the video, captures the timestamp, posts the question, renders the SSE answer and shows every state.
// All text from the server is written with textContent, never as HTML.
const root = document.querySelector('[data-ai-panel]');

if (root) {
    const strings = JSON.parse(root.dataset.strings || '{}');
    const lang = document.documentElement.lang || 'en';
    const max = Number(root.querySelector('[data-ai-input]').getAttribute('maxlength')) || 300;
    const q = (selector) => root.querySelector(selector);
    const thread = q('[data-ai-thread]');
    const notice = q('[data-ai-notice]');
    const form = q('[data-ai-form]');
    const input = q('[data-ai-input]');
    const send = q('[data-ai-send]');
    const counter = q('[data-ai-counter]');
    const announce = q('[data-ai-announce]');
    const actions = q('[data-ai-actions]');
    const starters = q('[data-ai-starters]');
    const starterList = q('[data-ai-starter-list]');
    const opener = document.querySelector('[data-ai-open]');
    const closeButton = q('[data-ai-close]');
    const media = document.querySelector('[data-player-media]');
    const simplerButton = q('[data-ai-action="simpler"]');
    const feedbackButtons = [...root.querySelectorAll('[data-ai-action="understood"], [data-ai-action="still"]')];

    let busy = false;
    let blocked = false;
    let statusLoaded = false;
    let conversationId = null;
    let position = null;
    let simplerUsed = false;
    let lastRequest = null;

    const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), text);
    const number = (value) => new Intl.NumberFormat(lang).format(value);
    const template = (name) => root.querySelector(`[data-ai-tpl="${name}"]`).content.firstElementChild.cloneNode(true);
    const say = (text) => {
        announce.textContent = '';
        window.setTimeout(() => { announce.textContent = text; }, 50);
    };
    // The close button is hidden on the wide layout, where the panel is a column that is always visible.
    const isSheet = () => closeButton !== null && window.getComputedStyle(closeButton).display !== 'none';

    const setState = (state) => {
        root.dataset.state = state;
        root.setAttribute('aria-busy', state === 'loading' || state === 'streaming' ? 'true' : 'false');
    };
    const clearNotice = () => { notice.replaceChildren(); };
    const showNotice = (message, { tone = 'info', retry = null, role = 'status' } = {}) => {
        clearNotice();
        const box = template('notice');
        box.className = box.className.replace(/bg-\w+ text-on-\w+/, tone === 'danger' ? 'bg-danger text-on-danger' : tone === 'warning' ? 'bg-warning text-on-warning' : 'bg-info text-on-info');
        box.setAttribute('role', role);
        box.querySelector('[data-ai-text]').textContent = message;
        const button = box.querySelector('[data-ai-retry]');
        if (retry) {
            button.hidden = false;
            button.addEventListener('click', retry);
        }
        notice.append(box);
        say(message);
    };

    const lock = (on) => {
        busy = on;
        // readOnly instead of disabled: a disabled field would drop keyboard focus out of the panel.
        input.readOnly = on || blocked;
        send.disabled = on || blocked;
        simplerButton.disabled = on || blocked || simplerUsed;
    };
    const disablePanel = (message, state) => {
        blocked = true;
        setState(state);
        lock(false);
        input.disabled = true;
        actions.hidden = true;
        starters.hidden = true;
        showNotice(message, { tone: 'warning' });
    };

    const formatTime = (seconds) => {
        const whole = Math.max(0, Math.floor(seconds));
        const h = Math.floor(whole / 3600);
        const m = Math.floor((whole % 3600) / 60);
        const s = String(whole % 60).padStart(2, '0');

        return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
    };
    const captureTimestamp = () => {
        if (!media || !Number.isFinite(media.currentTime)) {
            return;
        }
        position = Math.floor(media.currentTime);
        const chip = q('[data-ai-timestamp]');
        q('[data-ai-timestamp-text]').textContent = fill(strings.pausedAt, { time: formatTime(position) });
        chip.hidden = false;
    };

    const updateCounter = () => {
        counter.textContent = fill(strings.characters, { count: number([...input.value].length), max: number(max) });
    };

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const loadStatus = async () => {
        if (statusLoaded) {
            return;
        }
        statusLoaded = true;
        try {
            const response = await fetch(root.dataset.statusUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error('status');
            }
            const status = await response.json();
            if (!status.available) {
                disablePanel(status.message || strings.unavailable, status.reason === 'quota_exhausted' ? 'quota' : 'disabled');

                return;
            }
            const terms = Array.isArray(status.key_terms) ? status.key_terms : [];
            starterList.replaceChildren(...terms.map((term) => {
                const item = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.dir = 'auto';
                button.className = 'min-h-11 rounded-full border border-border bg-surface px-4 text-sm font-semibold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
                button.textContent = term;
                button.addEventListener('click', () => {
                    input.value = term.slice(0, max);
                    updateCounter();
                    input.focus();
                });
                item.append(button);

                return item;
            }));
            starters.hidden = terms.length === 0 || thread.childElementCount > 0;
        } catch {
            statusLoaded = false;
            showNotice(strings.networkError, { tone: 'danger', role: 'alert', retry: () => { clearNotice(); loadStatus(); } });
        }
    };

    const addUser = (text) => {
        const bubble = template('user');
        bubble.querySelector('[data-ai-text]').textContent = text;
        thread.append(bubble);
        thread.scrollTop = thread.scrollHeight;
    };
    const addAnswer = () => {
        const bubble = template('answer');
        thread.append(bubble);
        thread.scrollTop = thread.scrollHeight;

        return bubble;
    };
    const setPages = (bubble, pages) => {
        const holder = bubble.querySelector('[data-ai-pages]');
        const unique = [...new Set((pages || []).map(Number).filter((page) => Number.isInteger(page) && page > 0))];
        holder.replaceChildren(...unique.map((page) => {
            const label = fill(strings.sourcePage, { page: number(page) });
            const chip = document.createElement(root.dataset.pdfUrl ? 'a' : 'span');
            chip.className = 'inline-flex min-h-11 items-center gap-1 rounded-full bg-assistant px-3 text-sm font-medium text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
            chip.textContent = label;
            if (root.dataset.pdfUrl) {
                chip.href = `${root.dataset.pdfUrl}#page=${page}`;
                chip.target = '_blank';
                chip.rel = 'noopener';
                chip.setAttribute('aria-label', fill(strings.openSourcePage, { page: number(page) }));
            }

            return chip;
        }));
    };

    // Reads Server-Sent Events: "token" events carry text chunks, the final "result" event carries the structured reply.
    const readEvents = async (response, onEvent) => {
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        const flush = (block) => {
            let event = 'message';
            const data = [];
            block.split('\n').forEach((line) => {
                if (line.startsWith('event:')) {
                    event = line.slice(6).trim();
                } else if (line.startsWith('data:')) {
                    data.push(line.slice(5).replace(/^ /, ''));
                }
            });
            if (data.length > 0) {
                onEvent(event, JSON.parse(data.join('\n')));
            }
        };
        for (;;) {
            const { done, value } = await reader.read();
            if (done) {
                break;
            }
            buffer += decoder.decode(value, { stream: true }).replace(/\r\n/g, '\n');
            let index;
            while ((index = buffer.indexOf('\n\n')) !== -1) {
                flush(buffer.slice(0, index));
                buffer = buffer.slice(index + 2);
            }
        }
        if (buffer.trim() !== '') {
            flush(buffer);
        }
    };

    const showJsonState = (response, body, bubble) => {
        const state = body.state || '';
        const message = body.message || strings.unavailable;
        if (state === 'not_in_source' || state === 'out_of_scope') {
            conversationId = body.conversation_id || conversationId;
            const hint = bubble.querySelector('[data-ai-hint]');
            bubble.querySelector('[data-ai-text]').textContent = state === 'not_in_source' ? strings.notInSource : strings.outOfScope;
            hint.textContent = state === 'not_in_source' ? strings.notInSourceHint : strings.outOfScopeHint;
            hint.hidden = false;
            setState(state);
            say(bubble.querySelector('[data-ai-text]').textContent);

            return;
        }
        bubble.remove();
        if (response.status === 429) {
            let reset = '';
            if (body.reset_at) {
                const date = new Date(body.reset_at);
                reset = Number.isNaN(date.getTime()) ? '' : ` ${fill(strings.quotaReset, { time: new Intl.DateTimeFormat(lang, { timeStyle: 'short' }).format(date) })}`;
            }
            disablePanel(`${strings.quota}${reset}`, 'quota');
        } else if (response.status === 403 || (response.status === 409 && ['no_approved_document', 'not_recorded_lesson'].includes(state))) {
            disablePanel(message, 'disabled');
        } else if (state === 'fallback') {
            setState('fallback');
            showNotice(strings.unavailable, { tone: 'warning', retry: () => { clearNotice(); ask(lastRequest); } });
        } else if (response.status === 422) {
            const errors = Object.values(body.errors || {}).flat();
            setState('error');
            showNotice(errors[0] || strings.questionInvalid, { tone: 'danger', role: 'alert' });
        } else {
            // Conversation rules the server states itself (thread limit, one simpler answer per question).
            setState('error');
            showNotice(message, { tone: 'warning' });
            if (state === 'simpler_limit') {
                simplerUsed = true;
            }
        }
    };

    async function ask(request) {
        if (busy || blocked || !request) {
            return;
        }
        lastRequest = request;
        clearNotice();
        starters.hidden = true;
        actions.hidden = true;
        lock(true);
        setState('loading');
        const body = { mode: request.mode, video_position: position, conversation_id: conversationId };
        if (request.mode === 'normal') {
            body.question = request.question;
            if (!request.shown) {
                request.shown = true;
                addUser(request.question);
            }
        }
        const bubble = addAnswer();
        const text = bubble.querySelector('[data-ai-text]');
        const typing = template('typing');
        bubble.querySelector('[data-ai-pages]').before(typing);
        say(strings.loading);

        let streamed = false;
        try {
            const response = await fetch(root.dataset.askUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream, application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body),
            });
            typing.remove();
            if ((response.headers.get('Content-Type') || '').includes('text/event-stream') && response.ok) {
                await readEvents(response, (event, data) => {
                    if (event === 'token') {
                        if (!streamed) {
                            streamed = true;
                            setState('streaming');
                        }
                        text.textContent += data.text;
                        thread.scrollTop = thread.scrollHeight;
                    } else if (event === 'result') {
                        // The result carries the validated message; it replaces what was streamed.
                        text.textContent = data.message || text.textContent;
                        conversationId = data.conversation_id || conversationId;
                        setPages(bubble, data.source_pages);
                        setState('answer');
                        actions.hidden = false;
                        say(strings.answered);
                    }
                });
                if (root.dataset.state !== 'answer') {
                    throw new Error('incomplete');
                }
                simplerUsed = request.mode === 'simpler';
            } else {
                let data = {};
                try {
                    data = await response.json();
                } catch {
                    data = { state: 'fallback' };
                }
                showJsonState(response, data, bubble);
            }
        } catch {
            typing.remove();
            if (!streamed) {
                bubble.remove();
            }
            setState('error');
            showNotice(strings.networkError, { tone: 'danger', role: 'alert', retry: () => { clearNotice(); ask(lastRequest); } });
        } finally {
            lock(false);
        }
        // The question text stays in the field after a failure so it can be edited or retried.
        if (request.mode === 'normal' && ['answer', 'not_in_source', 'out_of_scope'].includes(root.dataset.state)) {
            input.value = '';
            updateCounter();
        }
    }

    const open = () => {
        root.dataset.open = 'true';
        opener?.setAttribute('aria-expanded', 'true');
        if (media && typeof media.pause === 'function') {
            media.pause();
        }
        captureTimestamp();
        loadStatus();
        (blocked ? thread : input).focus();
    };
    const close = () => {
        root.dataset.open = 'false';
        opener?.setAttribute('aria-expanded', 'false');
        opener?.focus();
    };

    opener?.addEventListener('click', open);
    closeButton?.addEventListener('click', close);
    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isSheet() && root.dataset.open === 'true') {
            event.stopPropagation();
            close();
        }
    });
    media?.addEventListener('pause', () => {
        if (root.dataset.open === 'true' || !isSheet()) {
            captureTimestamp();
        }
    });

    input.addEventListener('input', updateCounter);
    input.addEventListener('keydown', (event) => {
        // Enter sends; Shift+Enter writes a new line; never interrupt IME composition.
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const question = input.value.trim();
        if (question === '') {
            setState('error');
            showNotice(strings.emptyQuestion, { tone: 'warning', role: 'alert' });
            input.focus();

            return;
        }
        ask({ mode: 'normal', question: question.slice(0, max) });
    });
    simplerButton.addEventListener('click', () => {
        if (!simplerUsed) {
            ask({ mode: 'simpler' });
        }
    });
    // understood / still-not stay disabled until the feedback endpoints exist (T-P7-04); data-feedback-url is the hook.
    feedbackButtons.forEach((button) => {
        button.disabled = root.dataset.feedbackUrl === '';
    });

    updateCounter();
    // On the wide layout the panel is always visible: load the starter terms without waiting for an opener click.
    if (!isSheet()) {
        loadStatus();
    }
}
